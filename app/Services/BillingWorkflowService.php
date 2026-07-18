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
            [$target, $status] = $this->paymentReleaseTarget($destination, $invoice->facility_id);
            $this->workflow->transferPatient($handoff->visit, $target, $handoff->reason ?: 'Released after payment', $actor, $status, false, null, true);
            $handoff->update(['status' => 'released', 'released_by' => $actor->id, 'released_at' => now()]);
            $this->audit->record('billing_handoff_released', $handoff);
        });
    }

    public function releasePaidInvoice(Invoice $invoice, $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $invoice = Invoice::query()->with(['visit.destinationDepartment', 'handoffs.destinationDepartment'])->lockForUpdate()->findOrFail($invoice->id);
            $invoice = app(InvoiceStatusService::class)->recalculate($invoice);

            if ((float) $invoice->balance_amount > 0 || ! $invoice->visit) {
                return;
            }

            $pendingHandoff = $invoice->handoffs()
                ->where('status', 'pending_payment')
                ->oldest()
                ->first();

            if ($pendingHandoff) {
                $this->releaseToDestination($pendingHandoff, $actor);

                return;
            }

            $visit = $invoice->visit()->with('destinationDepartment')->first();
            $destination = $visit?->destinationDepartment;

            if (! $visit || ! $destination || ! $this->canReleaseVisitAfterPayment($visit)) {
                return;
            }

            [$target, $status] = $this->paymentReleaseTarget($destination, $invoice->facility_id);
            $this->workflow->transferPatient($visit, $target, 'Released after full payment', $actor, $status, false, null, true);
            $this->audit->record('billing_invoice_released_after_payment', $invoice);
        });
    }

    /**
     * @return array{0: Department, 1: VisitStatus}
     */
    private function paymentReleaseTarget(Department $destination, int $facilityId): array
    {
        if ($destination->requires_triage) {
            $triage = Department::query()
                ->where('facility_id', $facilityId)
                ->where('code', 'TRI')
                ->where('queue_enabled', true)
                ->first();

            if ($triage) {
                return [$triage, VisitStatus::InProgress];
            }
        }

        return [$destination, VisitStatus::InProgress];
    }

    private function canReleaseVisitAfterPayment($visit): bool
    {
        $status = $visit->visit_status?->value ?? $visit->visit_status;

        if (! in_array($status, [VisitStatus::AwaitingPayment->value, VisitStatus::Waiting->value], true)) {
            return false;
        }

        $billingDepartmentId = Department::query()
            ->where('facility_id', $visit->facility_id)
            ->where('code', 'BIL')
            ->value('id');

        if ($billingDepartmentId && (int) $visit->current_department_id === (int) $billingDepartmentId) {
            return true;
        }

        return $visit->queues()
            ->where('department_id', $billingDepartmentId)
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->exists();
    }
}
