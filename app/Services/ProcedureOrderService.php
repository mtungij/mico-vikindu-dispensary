<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Enums\ProcedureOrderStatus;
use App\Enums\ServiceType;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalProcedureOrder;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcedureOrderService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly VisitClosureService $visitClosure,
        private readonly WorkflowService $workflow,
    ) {}

    public function createOrder(ClinicalEncounter $encounter, array $data, $actor): ClinicalProcedureOrder
    {
        return DB::transaction(function () use ($encounter, $data, $actor) {
            $service = isset($data['service_id']) ? Service::query()->where('facility_id', $encounter->facility_id)->findOrFail($data['service_id']) : null;
            if ($service && $service->service_type !== ServiceType::Procedure) {
                throw ValidationException::withMessages(['service_id' => 'Huduma ya procedure pekee ndiyo inaruhusiwa.']);
            }
            $order = ClinicalProcedureOrder::query()->create([
                'facility_id' => $encounter->facility_id,
                'patient_id' => $encounter->patient_id,
                'visit_id' => $encounter->visit_id,
                'clinical_encounter_id' => $encounter->id,
                'service_id' => $service?->id,
                'ordered_by' => $actor->id,
                'procedure_name_snapshot' => $service?->name ?? $data['procedure_name_snapshot'],
                'instructions' => $data['instructions'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'status' => $encounter->visit->payer_type === PayerType::Cash && $service?->requires_payment ? ProcedureOrderStatus::AwaitingPayment : ProcedureOrderStatus::Ordered,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'procedure_order_created', 'subject_type' => $order::class, 'subject_id' => $order->id]);

            if ($service?->requires_payment) {
                $invoice = $encounter->visit->invoice ?: $this->invoices->createVisitInvoice($encounter->visit, [], $actor);
                $invoiceItem = $invoice->items()->where('reference_type', ClinicalProcedureOrder::class)->where('reference_id', $order->id)->first();
                if (! $invoiceItem) {
                    $invoiceItem = $this->invoices->addServiceItem($invoice, $service, $actor);
                    $invoiceItem->update(['reference_type' => ClinicalProcedureOrder::class, 'reference_id' => $order->id, 'metadata' => [...($invoiceItem->metadata ?? []), 'clinical_procedure_order_id' => $order->id]]);
                    ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'procedure_charge_created', 'subject_type' => $order::class, 'subject_id' => $order->id, 'new_values' => ['invoice_id' => $invoice->id, 'invoice_item_id' => $invoiceItem->id]]);
                }
                $order->update(['invoice_item_id' => $invoiceItem->id]);
                $this->invoices->calculateTotals($invoice);
            }

            return $order->refresh();
        });
    }

    public function releasePaidInvoice(\App\Models\Invoice $invoice, $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $invoice = $this->invoices->calculateTotals($invoice);
            if ((float) $invoice->balance_amount > 0 || $invoice->payment_status !== 'paid') return;
            ClinicalProcedureOrder::query()->where('facility_id', $invoice->facility_id)->where('visit_id', $invoice->visit_id)->where('status', ProcedureOrderStatus::AwaitingPayment)->with(['service', 'visit'])->lockForUpdate()->get()->each(function ($order) use ($actor, $invoice): void {
                $order->update(['status' => ProcedureOrderStatus::Ordered, 'updated_by' => $actor->id]);
                ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'procedure_payment_confirmed', 'subject_type' => $order::class, 'subject_id' => $order->id, 'new_values' => ['invoice_id' => $invoice->id]]);
                if ($order->service?->department && $order->service->department->queue_enabled) {
                    $this->workflow->createQueue($order->visit, $order->service->department, $actor, VisitStatus::AwaitingDepartment, 'Procedure released after full payment', true, false);
                }
                ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'procedure_released', 'subject_type' => $order::class, 'subject_id' => $order->id]);
            });
        });
    }

    public function cancelOrder(ClinicalProcedureOrder $order, string $reason, $actor): ClinicalProcedureOrder
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu inahitajika.']);
        }
        $order->update(['status' => ProcedureOrderStatus::Cancelled, 'updated_by' => $actor->id, 'notes' => trim(($order->notes ? $order->notes."\n" : '').'Cancelled: '.$reason)]);
        $this->finishPatientFacingWorkflowIfTerminal($order, $actor);

        return $order->refresh();
    }

    public function completeOrder(ClinicalProcedureOrder $order, $actor, ?string $notes = null): ClinicalProcedureOrder
    {
        return DB::transaction(function () use ($order, $actor, $notes): ClinicalProcedureOrder {
            $order = ClinicalProcedureOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (in_array($order->status, [ProcedureOrderStatus::Completed, ProcedureOrderStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['procedure' => 'Procedure order tayari imefungwa.']);
            }
            if ($order->facility_id !== currentFacility()?->id || ! $actor->belongsToCurrentFacility()) abort(403);
            if (! $actor->can('procedure-orders.view')) abort(403);
            if ($order->status === ProcedureOrderStatus::AwaitingPayment && ! $actor->can('procedure-orders.override-payment')) {
                throw ValidationException::withMessages(['procedure' => 'Procedure haiwezi kufanywa kabla ya malipo kamili bila ruhusa ya override.']);
            }
            $order->update([
                'status' => ProcedureOrderStatus::Completed,
                'performed_at' => now(),
                'performed_by' => $actor->id,
                'notes' => $notes ?? $order->notes,
                'updated_by' => $actor->id,
            ]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'procedure_order_completed', 'subject_type' => $order::class, 'subject_id' => $order->id]);
            $this->finishPatientFacingWorkflowIfTerminal($order, $actor);

            return $order->refresh();
        });
    }

    private function finishPatientFacingWorkflowIfTerminal(ClinicalProcedureOrder $order, $actor): void
    {
        if (ClinicalProcedureOrder::query()
            ->where('visit_id', $order->visit_id)
            ->whereKeyNot($order->id)
            ->whereIn('status', ['ordered', 'awaiting_payment', 'scheduled', 'in_progress'])
            ->exists()) {
            return;
        }

        $departmentIds = ClinicalProcedureOrder::query()
            ->where('visit_id', $order->visit_id)
            ->with('service:id,department_id')
            ->get()
            ->pluck('service.department_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $this->visitClosure->completeQueuesForDepartments($order->visit, $departmentIds, $actor);
        $this->visitClosure->evaluate($order->visit->refresh(), $actor);
    }
}
