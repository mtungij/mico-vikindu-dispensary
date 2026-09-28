<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\InvoiceItem;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LaboratoryOrderItemDecisionService
{
    public const REASONS = [
        'patient_declined' => 'Patient declined',
        'sample_unavailable' => 'Sample unavailable',
        'insufficient_sample' => 'Insufficient sample',
        'test_unavailable' => 'Test or reagent unavailable',
        'no_longer_required' => 'Clinically no longer required',
        'ordered_in_error' => 'Ordered in error',
        'patient_left' => 'Patient left facility',
        'other' => 'Other',
    ];

    public function __construct(
        private readonly BillingChargeService $charges,
        private readonly LaboratoryOrderStatusService $statuses,
        private readonly VisitClosureService $closure,
        private readonly WorkflowService $workflow,
    ) {}

    public function decide(LaboratoryOrderItem $item, string $decision, string $reasonCode, ?string $detail, $actor): LaboratoryOrderItem
    {
        if (! in_array($decision, ['cancelled', 'not_performed'], true)
            || ! array_key_exists($reasonCode, self::REASONS)
            || (in_array($reasonCode, ['other', 'ordered_in_error', 'no_longer_required'], true) && blank($detail))) {
            throw ValidationException::withMessages(['reason' => 'Select a valid reason and add details when required.']);
        }

        return DB::transaction(function () use ($item, $decision, $reasonCode, $detail, $actor): LaboratoryOrderItem {
            $orderId = $item->laboratory_order_id;
            $visitId = LaboratoryOrder::query()->whereKey($orderId)->value('visit_id');
            $visit = Visit::query()->lockForUpdate()->findOrFail($visitId);
            $order = LaboratoryOrder::query()->lockForUpdate()->findOrFail($orderId);
            Gate::forUser($actor)->authorize('decideItem', $order);
            abort_unless($actor->belongsToCurrentFacility()
                && $order->facility_id === currentFacility()?->id
                && $order->facility_id === $visit->facility_id
                && $order->visit_id === $visit->id, 403);
            $item = LaboratoryOrderItem::query()->lockForUpdate()->findOrFail($item->id);
            abort_unless($item->laboratory_order_id === $order->id, 404);
            if (in_array($item->status, ['cancelled', 'not_performed', 'entered_in_error'], true)) {
                if ($item->status === $decision) {
                    return $item;
                }
                throw ValidationException::withMessages(['item' => 'This test already has a different terminal decision.']);
            }
            if (in_array($item->result_status, ['pending_verification', 'verified', 'released'], true)
                || $item->results()->exists()) {
                throw ValidationException::withMessages(['item' => 'A result already exists. Use the controlled result correction workflow.']);
            }
            $charge = $item->invoice_item_id
                ? InvoiceItem::query()->lockForUpdate()->findOrFail($item->invoice_item_id)
                : null;
            if ($charge) {
                abort_unless($charge->facility_id === $order->facility_id
                    && $charge->visit_id === $visit->id
                    && $charge->reference_type === LaboratoryOrder::class
                    && (int) $charge->reference_id === $order->id, 403);
                if (! in_array($charge->status, ['cancelled', 'reversed'], true)) {
                    $this->charges->cancelCharge($charge, $actor, self::REASONS[$reasonCode].($detail ? ': '.$detail : ''), 'laboratory');
                }
            }
            $old = $item->status;
            $item->update([
                'status' => $decision,
                'terminal_reason_code' => $reasonCode,
                'terminal_reason' => $detail,
                'terminal_decided_at' => now(),
                'terminal_decided_by' => $actor->id,
            ]);
            $this->statuses->recalculate($order, $actor);
            if ($order->refresh()->status->value === 'completed') {
                $otherOrders = LaboratoryOrder::query()->where('visit_id', $visit->id)->where('facility_id', $visit->facility_id)
                    ->whereNotIn('status', ['completed', 'cancelled'])->exists();
                if (! $otherOrders) {
                    $this->closure->completeDepartmentQueues($visit, 'LAB', $actor);
                    $activeEncounter = $visit->activeClinicalEncounter;
                    if ($activeEncounter && ! $order->isDirectLaboratory()) {
                        $opdQueue = $visit->queues()->where('department_id', $activeEncounter->department_id)
                            ->whereIn('queue_status', ['waiting', 'called', 'serving'])->latest()->first();
                        $this->workflow->updateCurrentDepartment($visit, $activeEncounter->department, $actor, $opdQueue);
                        $this->workflow->updateVisitStatus($visit->refresh(), VisitStatus::InConsultation, $actor, $opdQueue);
                    }
                }
            }
            $this->closure->evaluate($visit, $actor);
            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => $decision === 'cancelled' ? 'laboratory_order_item_cancelled' : 'laboratory_order_item_not_performed',
                'subject_type' => $item::class,
                'subject_id' => $item->id,
                'old_values' => ['status' => $old],
                'new_values' => [
                    'facility_id' => $order->facility_id,
                    'visit_id' => $visit->id,
                    'laboratory_order_id' => $order->id,
                    'status' => $decision,
                    'reason_code' => $reasonCode,
                    'reason' => $detail,
                    'invoice_item_id' => $charge?->id,
                    'terminal_decided_at' => $item->terminal_decided_at,
                ],
            ]);

            return $item->refresh();
        });
    }
}
