<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Enums\ProcedureOrderStatus;
use App\Enums\ServiceType;
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
    ) {}

    public function createOrder(ClinicalEncounter $encounter, array $data, $actor): ClinicalProcedureOrder
    {
        return DB::transaction(function () use ($encounter, $data, $actor) {
            $service = isset($data['service_id']) ? Service::query()->where('facility_id', $encounter->facility_id)->findOrFail($data['service_id']) : null;
            if ($service && $service->service_type !== ServiceType::Procedure) {
                throw ValidationException::withMessages(['service_id' => 'Huduma ya procedure pekee ndiyo inaruhusiwa.']);
            }
            $invoiceItem = null;
            if ($service?->requires_payment) {
                $invoice = $encounter->visit->invoice ?: $this->invoices->createVisitInvoice($encounter->visit, [], $actor);
                $invoiceItem = $this->invoices->addServiceItem($invoice, $service, $actor);
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
                'invoice_item_id' => $invoiceItem?->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'procedure_order_created', 'subject_type' => $order::class, 'subject_id' => $order->id]);

            return $order;
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
