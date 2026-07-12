<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use App\Models\FacilitySetting;

class InsuranceClaimValidationService
{
    public function __construct(protected InsuranceAuditService $audit) {}

    public function validateClaim(InsuranceClaim $claim): array
    {
        $errors = [];
        if (! $claim->membership?->membership_number) $errors[] = 'Missing membership number.';
        if (! $claim->primary_diagnosis_code && $this->setting('insurance_require_primary_diagnosis', true)) $errors[] = 'Missing primary diagnosis.';
        if ($claim->items()->count() === 0) $errors[] = 'Claim has no items.';
        foreach ($claim->items as $item) {
            if ($this->setting('insurance_block_unmapped_service_codes', true) && ! $item->payer_service_code) $errors[] = 'Missing payer service code for '.$item->description_snapshot.'.';
            if ($item->claimed_amount <= 0) $errors[] = 'Invalid claim amount for '.$item->description_snapshot.'.';
        }

        $claim->update([
            'status' => $errors ? 'validation_failed' : 'ready',
            'validated_by' => auth()->id(),
            'validated_at' => now(),
        ]);
        $this->audit->record($errors ? 'claim_validation_failed' : 'claim_marked_ready', $claim, ['errors' => $errors]);

        return ['valid' => $errors === [], 'errors' => $errors, 'warnings' => []];
    }

    protected function setting(string $key, bool $default): bool
    {
        $setting = FacilitySetting::query()->where('facility_id', currentFacility()?->id)->where('key', $key)->first();

        return $setting ? filter_var($setting->value, FILTER_VALIDATE_BOOL) : $default;
    }
}
