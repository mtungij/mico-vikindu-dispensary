<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PatientInsuranceMembership;
use Illuminate\Support\Facades\DB;

class InsuranceClaimPreparationService
{
    public function __construct(protected InsuranceClaimNumberService $numbers, protected InsuranceAuditService $audit) {}

    public function findClaimableInvoiceItems(Invoice $invoice)
    {
        return $invoice->items()
            ->where('insurance_amount', '>', 0)
            ->whereNotIn('status', ['cancelled', 'reversed'])
            ->get();
    }

    public function prepareVisitClaim(Invoice $invoice, PatientInsuranceMembership $membership, string $claimType = 'combined_visit'): InsuranceClaim
    {
        return DB::transaction(function () use ($invoice, $membership, $claimType): InsuranceClaim {
            $existing = InsuranceClaim::query()->where('facility_id', $invoice->facility_id)->where('invoice_id', $invoice->id)->whereNull('parent_claim_id')->first();
            if ($existing) return $existing;

            $claim = InsuranceClaim::query()->create([
                'facility_id' => $invoice->facility_id,
                'insurance_provider_id' => $membership->insurance_provider_id,
                'insurance_scheme_id' => $membership->insurance_scheme_id,
                'benefit_package_id' => $membership->benefit_package_id,
                'membership_id' => $membership->id,
                'patient_id' => $invoice->patient_id,
                'visit_id' => $invoice->visit_id,
                'invoice_id' => $invoice->id,
                'claim_number' => $this->numbers->claim($invoice->facility_id),
                'claim_type' => $claimType,
                'service_date_from' => $invoice->issued_at?->toDateString() ?? today(),
                'service_date_to' => $invoice->issued_at?->toDateString() ?? today(),
                'status' => 'draft',
                'currency' => $invoice->currency,
                'prepared_by' => auth()->id() ?? $membership->created_by,
                'prepared_at' => now(),
                'created_by' => auth()->id() ?? $membership->created_by,
            ]);

            $invoice->items()->where('insurance_amount', '>', 0)->whereNotIn('status', ['cancelled','reversed'])->each(function (InvoiceItem $item) use ($claim): void {
                $claim->items()->create([
                    'facility_id' => $claim->facility_id,
                    'invoice_item_id' => $item->id,
                    'service_id' => $item->service_id,
                    'item_type' => $item->item_type,
                    'service_code_snapshot' => $item->service?->code,
                    'payer_service_code' => $item->metadata['payer_service_code'] ?? null,
                    'description_snapshot' => $item->description,
                    'service_date' => $claim->service_date_from,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'gross_amount' => $item->total_amount,
                    'patient_amount' => $item->patient_amount,
                    'claimed_amount' => $item->insurance_amount,
                    'coverage_status' => $item->claimable_status === 'claimable' ? 'covered' : ($item->claimable_status ?: 'not_configured'),
                    'status' => 'draft',
                    'metadata' => ['invoice_item_status' => $item->status],
                ]);
            });

            $this->calculateTotals($claim);
            $this->audit->record('claim_prepared', $claim);

            return $claim->refresh();
        });
    }

    public function calculateTotals(InsuranceClaim $claim): void
    {
        $items = $claim->items;
        $claim->update([
            'gross_amount' => $items->sum('gross_amount'),
            'patient_amount' => $items->sum('patient_amount'),
            'payer_claimed_amount' => $items->sum('claimed_amount'),
            'outstanding_amount' => max(0, $items->sum('claimed_amount') - (float) $claim->paid_amount),
        ]);
    }
}
