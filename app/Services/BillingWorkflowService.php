<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\VisitPaymentHandoff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingWorkflowService
{
    public function __construct(private readonly WorkflowService $workflow, private readonly BillingAuditService $audit) {}

    public function createPaymentHandoff(Invoice $invoice, ?Department $source, Department $destination, string $reason, $actor): VisitPaymentHandoff
    {
        $handoff = VisitPaymentHandoff::query()->create([
            'facility_id' => $invoice->facility_id,
            'patient_id' => $invoice->patient_id,
            'visit_id' => $invoice->visit_id,
            'invoice_id' => $invoice->id,
            'source_department_id' => $source?->id,
            'destination_department_id' => $destination->id,
            'reason' => $reason,
            'required_patient_amount' => $invoice->balance_amount,
            'status' => 'pending_payment',
            'priority' => $invoice->visit?->priority?->value ?? 'normal',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('billing_handoff_created', $handoff);

        return $handoff;
    }

    public function releaseToDestination(VisitPaymentHandoff $handoff, $actor): void
    {
        DB::transaction(function () use ($handoff, $actor): void {
            $handoff = VisitPaymentHandoff::query()->lockForUpdate()->findOrFail($handoff->id);
            if ($handoff->status === 'released') throw ValidationException::withMessages(['handoff' => 'Handoff imeshatumika.']);
            $invoice = app(InvoiceStatusService::class)->recalculate($handoff->invoice);
            if ((float) $invoice->balance_amount > 0) throw ValidationException::withMessages(['invoice' => 'Invoice bado ina salio.']);
            $destination = $handoff->destinationDepartment;
            if (! $destination) throw ValidationException::withMessages(['destination' => 'Destination haijasanidiwa.']);
            $this->workflow->transferPatient($handoff->visit, $destination, $handoff->reason ?: 'Released after payment', $actor, VisitStatus::Waiting, true);
            $handoff->update(['status' => 'released', 'released_by' => $actor->id, 'released_at' => now()]);
            $this->audit->record('billing_handoff_released', $handoff);
        });
    }
}
