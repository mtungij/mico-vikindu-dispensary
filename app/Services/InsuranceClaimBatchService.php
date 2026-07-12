<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimBatch;
use App\Models\InsuranceProvider;
use Illuminate\Support\Facades\DB;

class InsuranceClaimBatchService
{
    public function __construct(protected InsuranceClaimNumberService $numbers, protected InsuranceAuditService $audit) {}

    public function createBatch(InsuranceProvider $provider, ?int $schemeId = null): InsuranceClaimBatch
    {
        $batch = InsuranceClaimBatch::query()->create([
            'facility_id' => currentFacility()->id,
            'insurance_provider_id' => $provider->id,
            'insurance_scheme_id' => $schemeId,
            'batch_number' => $this->numbers->batch(currentFacility()->id),
            'batch_date' => today(),
            'status' => 'draft',
            'prepared_by' => auth()->id(),
            'prepared_at' => now(),
        ]);
        $this->audit->record('claim_batch_created', $batch);

        return $batch;
    }

    public function addClaim(InsuranceClaimBatch $batch, InsuranceClaim $claim): void
    {
        DB::transaction(function () use ($batch, $claim): void {
            $locked = InsuranceClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'ready') throw new \RuntimeException('Only ready claims can be batched.');
            if ($locked->insurance_provider_id !== $batch->insurance_provider_id) throw new \RuntimeException('Claim provider does not match batch provider.');
            if ($locked->batch_id) throw new \RuntimeException('Claim is already batched.');
            $locked->update(['batch_id' => $batch->id, 'status' => 'batched']);
            $this->calculateTotals($batch);
            $this->audit->record('claim_added_to_batch', $locked);
        });
    }

    public function calculateTotals(InsuranceClaimBatch $batch): void
    {
        $claims = $batch->claims()->get();
        $batch->update([
            'claims_count' => $claims->count(),
            'total_claimed_amount' => $claims->sum('payer_claimed_amount'),
            'total_approved_amount' => $claims->sum('approved_amount'),
            'total_paid_amount' => $claims->sum('paid_amount'),
        ]);
    }
}
