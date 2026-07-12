<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use Illuminate\Support\Facades\DB;

class InsuranceClaimCorrectionService
{
    public function __construct(protected InsuranceClaimNumberService $numbers, protected InsuranceAuditService $audit) {}

    public function createCorrection(InsuranceClaim $claim, string $reason): InsuranceClaim
    {
        return DB::transaction(function () use ($claim, $reason): InsuranceClaim {
            $copy = $claim->replicate(['claim_number', 'status', 'version', 'parent_claim_id', 'batch_id', 'submitted_at', 'submitted_by']);
            $copy->claim_number = $this->numbers->claim($claim->facility_id);
            $copy->status = 'draft';
            $copy->version = $claim->version + 1;
            $copy->parent_claim_id = $claim->id;
            $copy->correction_reason = $reason;
            $copy->resubmission_count = $claim->resubmission_count + 1;
            $copy->created_by = auth()->id();
            $copy->save();
            foreach ($claim->items as $item) {
                $copy->items()->create($item->replicate(['insurance_claim_id', 'status'])->fill(['status' => 'draft'])->toArray());
            }
            $claim->update(['status' => 'correction_required']);
            $this->audit->record('claim_correction_created', $copy);

            return $copy;
        });
    }
}
