<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Enums\PrescriptionStatus;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\InsurancePreAuthorization;
use App\Models\Invoice;
use App\Models\PatientInsuranceMembership;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrescriptionBillingService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly BillingChargeService $charges,
        private readonly InvoiceStatusService $statuses,
        private readonly WorkflowService $workflow,
        private readonly ServicePricingService $pricing,
        private readonly InsuranceCoverageService $coverage,
        private readonly VisitClosureService $visitClosure,
    ) {}

    public function bill(Prescription $prescription, $actor): Prescription
    {
        return DB::transaction(function () use ($prescription, $actor): Prescription {
            $prescription = Prescription::query()
                ->with(['items.medicine.service', 'visit.invoice', 'encounter'])
                ->lockForUpdate()
                ->findOrFail($prescription->id);
            abort_unless($prescription->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);
            if ($prescription->encounter?->visit_id !== $prescription->visit_id
                || $prescription->encounter?->facility_id !== $prescription->facility_id) {
                throw ValidationException::withMessages(['prescription' => 'Prescription encounter and visit details are inconsistent.']);
            }
            $invoice = $prescription->visit->invoice
                ?: $this->invoices->createVisitInvoice($prescription->visit, [], $actor);

            foreach ($prescription->items as $item) {
                if (blank($item->dose) || blank($item->frequency) || (int) $item->duration_value < 1 || blank($item->duration_unit)) {
                    throw ValidationException::withMessages(['prescription' => "Medicine {$item->medication_name} has invalid dose, frequency, or duration."]);
                }
                if ((float) $item->quantity <= 0) {
                    throw ValidationException::withMessages(['prescription' => 'Every prescribed medicine must have a quantity greater than zero.']);
                }
                if (! $item->medicine || ! $item->medicine->is_active) {
                    throw ValidationException::withMessages(['prescription' => "{$item->medication_name} is not linked to an active medicine."]);
                }
                if (! $item->medicine->service || ! $item->medicine->service->is_active) {
                    throw ValidationException::withMessages([
                        'prescription' => "Medicine {$item->medication_name} has no active billing service or price.",
                    ]);
                }

                $price = $this->pricing->getCurrentPrice(
                    $item->medicine->service,
                    $invoice->payer_type,
                    $invoice->insurance_provider_id ?? $invoice->patientPayerProfile?->insurance_provider_id,
                    $invoice->corporate_account_id ?? $invoice->patientPayerProfile?->corporate_account_id,
                );
                if (! $price && $item->medicine->service->requires_payment) {
                    throw ValidationException::withMessages([
                        'prescription' => "Medicine {$item->medication_name} has no active billing service or price.",
                    ]);
                }

                [$payerSplit, $coverageAttributes] = $this->medicinePayerSplit(
                    $prescription,
                    $item,
                    (float) ($price?->amount ?? 0) * (float) $item->quantity,
                );

                $invoiceItem = $this->charges->addServiceCharge(
                    $invoice,
                    $item->medicine->service,
                    $actor,
                    $item,
                    (float) $item->quantity,
                    ['source' => 'prescription', 'prescription_id' => $prescription->id, 'medicine_id' => $item->medicine_id],
                    $payerSplit,
                    $coverageAttributes,
                );
                $item->update([
                    'service_id' => $item->medicine->service_id,
                    'invoice_item_id' => $invoiceItem->id,
                    'remaining_quantity' => max(0, (float) $item->quantity - (float) $item->dispensed_quantity),
                    'unit_price_snapshot' => $invoiceItem->unit_price,
                    'patient_amount' => $invoiceItem->patient_amount,
                    'insurance_amount' => $invoiceItem->insurance_amount,
                    'payer_amount' => $invoiceItem->payer_amount,
                ]);
                $this->audit($actor, 'medicine_billed', $prescription, [
                    'invoice_id' => $invoice->id,
                    'invoice_item_id' => $invoiceItem->id,
                    'prescription_item_id' => $item->id,
                ]);
            }

            $invoice = $this->statuses->recalculate($invoice);
            $clearance = $this->clearance($prescription->refresh());
            $prescription->update([
                'status' => ! $clearance['cleared']
                    ? PrescriptionStatus::AwaitingPayment
                    : PrescriptionStatus::Prescribed,
                'updated_by' => $actor->id,
            ]);

            return $prescription->refresh();
        });
    }

    public function releasePaidInvoice(Invoice $invoice, $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $invoice = $this->statuses->recalculate($invoice);

            $prescriptions = Prescription::query()
                ->where('visit_id', $invoice->visit_id)
                ->where('status', PrescriptionStatus::AwaitingPayment->value)
                ->whereHas('items.invoiceItem', fn ($query) => $query->where('invoice_id', $invoice->id))
                ->lockForUpdate()
                ->get();

            $released = false;
            foreach ($prescriptions as $prescription) {
                if ($this->isCleared($prescription)) {
                    $this->releasePrescription($prescription, $actor);
                    $released = true;
                }
            }

            if ($released && $invoice->visit) {
                $this->visitClosure->evaluate($invoice->visit->refresh(), $actor);
            }
        });
    }

    /** @return array{amount_due: float, amount_paid: float, patient_balance: float, insurance_covered_amount: float, authorization_pending: bool, cleared: bool} */
    public function clearance(Prescription $prescription): array
    {
        $items = $prescription->items()
            ->with('invoiceItem')
            ->get()
            ->pluck('invoiceItem')
            ->filter(fn ($item) => $item && ! in_array($item->status, ['cancelled', 'reversed'], true));
        $amountDue = round((float) $items->sum('patient_amount'), 2);
        $amountPaid = round((float) $items->sum(fn ($item) => min((float) $item->patient_amount, (float) $item->paid_amount)), 2);
        $balance = max(0, round($amountDue - $amountPaid, 2));
        $authorizationPending = $items->contains(function ($item): bool {
            $snapshot = $item->coverage_snapshot ?? [];

            return (bool) ($snapshot['requires_pre_authorization'] ?? false)
                && ! $item->insurance_pre_authorization_id;
        });

        return [
            'amount_due' => $amountDue,
            'amount_paid' => $amountPaid,
            'patient_balance' => $balance,
            'insurance_covered_amount' => round((float) $items->sum('insurance_amount'), 2),
            'authorization_pending' => $authorizationPending,
            'cleared' => $items->isNotEmpty() && $balance <= 0.005 && ! $authorizationPending,
        ];
    }

    public function isCleared(Prescription $prescription): bool
    {
        return $this->clearance($prescription)['cleared'];
    }

    public function releasePrescription(Prescription $prescription, $actor): void
    {
        $prescription = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);
        if (! $this->isCleared($prescription)) {
            return;
        }

        if ($prescription->status === PrescriptionStatus::AwaitingPayment) {
            $prescription->update(['status' => PrescriptionStatus::Prescribed, 'updated_by' => $actor->id]);
        }
        if (! in_array($prescription->status, [PrescriptionStatus::Prescribed, PrescriptionStatus::PartiallyDispensed], true)) {
            return;
        }

        $pharmacy = Department::query()
            ->where('facility_id', $prescription->facility_id)
            ->where('code', 'PHA')
            ->where('is_active', true)
            ->where('queue_enabled', true)
            ->first();
        if (! $pharmacy) {
            throw ValidationException::withMessages(['destination' => 'Pharmacy queue is not configured.']);
        }

        $terminalVisit = in_array($prescription->visit->visit_status, [VisitStatus::Referred, VisitStatus::Cancelled, VisitStatus::Discharged], true);
        if ($terminalVisit) {
            $this->audit($actor, 'medicine_payment_cleared', $prescription, [
                'invoice_id' => $prescription->visit->invoice?->id,
                'pharmacy_queue_suppressed' => true,
                'visit_status' => $prescription->visit->visit_status->value,
            ]);

            return;
        }
        $queue = $this->workflow->createQueue(
            $prescription->visit,
            $pharmacy,
            $actor,
            VisitStatus::AwaitingPharmacy,
            'Medicine payment cleared',
            true,
            true,
        );
        $this->audit($actor, 'medicine_payment_cleared', $prescription, [
            'invoice_id' => $prescription->visit->invoice?->id,
            'pharmacy_queue_id' => $queue?->id,
        ]);
        if ($queue?->wasRecentlyCreated) {
            $this->audit($actor, 'pharmacy_queue_created', $prescription, ['pharmacy_queue_id' => $queue->id]);
        }
    }

    private function medicinePayerSplit(Prescription $prescription, PrescriptionItem $item, float $gross): array
    {
        if ($prescription->visit->payer_type !== PayerType::Insurance) {
            return [null, []];
        }

        $profile = $prescription->visit->payerProfile;
        $membership = PatientInsuranceMembership::query()
            ->where('facility_id', $prescription->facility_id)
            ->where('patient_id', $prescription->patient_id)
            ->where('insurance_provider_id', $profile?->insurance_provider_id)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', today()))
            ->first();

        $coverage = $membership
            ? $this->coverage->resolveMedicineCoverage($membership, $item->medicine, $gross)
            : ['coverage_status' => 'not_configured', 'covered' => false, 'patient_amount' => $gross, 'payer_amount' => 0.0];
        $authorization = null;
        if (($coverage['requires_pre_authorization'] ?? false) && $membership) {
            $authorization = InsurancePreAuthorization::query()
                ->where('facility_id', $prescription->facility_id)
                ->where('patient_id', $prescription->patient_id)
                ->where('visit_id', $prescription->visit_id)
                ->where('membership_id', $membership->id)
                ->where('status', 'approved')
                ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
                ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', today()))
                ->first();
        }

        $insuranceAmount = (float) ($coverage['payer_amount'] ?? 0);
        $patientAmount = (float) ($coverage['patient_amount'] ?? $gross);

        return [[
            'patient_amount' => $patientAmount,
            'insurance_amount' => $insuranceAmount,
            'corporate_amount' => 0.0,
            'payer_amount' => $insuranceAmount,
            'net_amount' => $gross,
        ], [
            'insurance_provider_id' => $membership?->insurance_provider_id,
            'insurance_scheme_id' => $membership?->insurance_scheme_id,
            'patient_insurance_membership_id' => $membership?->id,
            'insurance_pre_authorization_id' => $authorization?->id,
            'coverage_percentage' => $coverage['coverage_percentage'] ?? 0,
            'claimable_status' => ($coverage['covered'] ?? false) ? 'claimable' : 'not_claimable',
            'coverage_snapshot' => $coverage,
        ]];
    }

    private function audit($actor, string $event, Prescription $prescription, array $extra = []): void
    {
        ActivityLog::query()->create([
            'user_id' => $actor?->id,
            'event' => $event,
            'subject_type' => $prescription::class,
            'subject_id' => $prescription->id,
            'new_values' => [
                'facility_id' => $prescription->facility_id,
                'patient_id' => $prescription->patient_id,
                'visit_id' => $prescription->visit_id,
                'prescription_id' => $prescription->id,
                ...$extra,
            ],
        ]);
    }
}
