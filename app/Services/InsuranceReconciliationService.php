<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use App\Models\InsuranceProvider;

class InsuranceReconciliationService
{
    public function calculateClaimOutstanding(InsuranceClaim $claim): float
    {
        return max(0, (float) $claim->payer_claimed_amount - (float) $claim->paid_amount);
    }

    public function calculateProviderOutstanding(InsuranceProvider $provider): float
    {
        return (float) InsuranceClaim::query()
            ->where('facility_id', currentFacility()?->id)
            ->where('insurance_provider_id', $provider->id)
            ->whereIn('status', ['submitted','approved','partially_approved','partially_paid'])
            ->sum('outstanding_amount');
    }
}
