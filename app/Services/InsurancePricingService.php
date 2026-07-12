<?php

namespace App\Services;

use App\Models\InsuranceContractPrice;
use App\Models\PatientInsuranceMembership;
use App\Models\Service;

class InsurancePricingService
{
    public function resolvePrice(Service $service, PatientInsuranceMembership $membership, mixed $date = null): ?InsuranceContractPrice
    {
        $date = $date ? now()->parse($date)->toDateString() : today()->toDateString();

        return InsuranceContractPrice::query()
            ->where('facility_id', $membership->facility_id)
            ->where('insurance_provider_id', $membership->insurance_provider_id)
            ->where('service_id', $service->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByRaw('insurance_scheme_id is null')
            ->orderByRaw('benefit_package_id is null')
            ->first();
    }
}
