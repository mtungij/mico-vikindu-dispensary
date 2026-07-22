<?php

namespace App\Services;

use App\Enums\CoverageStatus;
use App\Enums\PayerType;
use App\Models\ClinicalEncounter;
use App\Models\CorporateAccount;
use App\Models\InsurancePreAuthorization;
use App\Models\InsuranceProvider;
use App\Models\PatientPayerProfile;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class LaboratoryCoverageService
{
    public function __construct(private readonly ServicePricingService $pricing) {}

    /** @param Collection<int, Service> $services */
    public function ensureApproved(ClinicalEncounter $encounter, Collection $services): void
    {
        $visit = $encounter->visit;

        if (! in_array($visit->payer_type, [PayerType::Insurance, PayerType::Corporate, PayerType::Exempted], true)) {
            return;
        }

        $profile = PatientPayerProfile::query()
            ->whereKey($visit->patient_payer_profile_id)
            ->where('facility_id', $visit->facility_id)
            ->where('patient_id', $visit->patient_id)
            ->where('payer_type', $visit->payer_type)
            ->first();

        if (! $profile || $profile->coverage_status !== CoverageStatus::Active || ! $this->isCurrentlyValid($profile)) {
            throw ValidationException::withMessages([
                'payer' => 'Payer coverage must be active, verified, and valid before laboratory services can bypass payment.',
            ]);
        }

        if ($visit->payer_type === PayerType::Insurance) {
            $this->ensureInsuranceApproved($encounter, $profile, $services);
        }

        if ($visit->payer_type === PayerType::Corporate) {
            $this->ensureCorporateApproved($profile, $services);
        }
    }

    /** @param Collection<int, Service> $services */
    private function ensureInsuranceApproved(ClinicalEncounter $encounter, PatientPayerProfile $profile, Collection $services): void
    {
        $provider = InsuranceProvider::query()
            ->whereKey($profile->insurance_provider_id)
            ->where('facility_id', $encounter->facility_id)
            ->where('is_active', true)
            ->first();

        if (! $provider) {
            throw ValidationException::withMessages(['payer' => 'The insurance provider is not active for this facility.']);
        }

        foreach ($services as $service) {
            $this->pricing->validatePriceAvailability($service, PayerType::Insurance, $provider->id);
        }

        if (! $provider->requires_pre_authorization) {
            return;
        }

        $approved = InsurancePreAuthorization::query()
            ->where('facility_id', $encounter->facility_id)
            ->where('patient_id', $encounter->patient_id)
            ->where('visit_id', $encounter->visit_id)
            ->where('insurance_provider_id', $provider->id)
            ->where('status', 'approved')
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', today()))
            ->exists();

        if (! $approved) {
            throw ValidationException::withMessages(['payer' => 'Approved insurance pre-authorization is required for these laboratory services.']);
        }
    }

    /** @param Collection<int, Service> $services */
    private function ensureCorporateApproved(PatientPayerProfile $profile, Collection $services): void
    {
        $account = CorporateAccount::query()
            ->whereKey($profile->corporate_account_id)
            ->where('facility_id', $profile->facility_id)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages(['payer' => 'The corporate credit account is not active or approved.']);
        }

        $total = 0.0;
        foreach ($services as $service) {
            $this->pricing->validatePriceAvailability($service, PayerType::Corporate, null, $account->id);
            $total += (float) $this->pricing->resolvePriceForPatient($service, PayerType::Corporate, null, $account->id);
        }

        if ((float) $account->credit_limit <= 0 || $total > (float) $account->credit_limit) {
            throw ValidationException::withMessages(['payer' => 'The corporate credit approval does not cover the selected laboratory services.']);
        }
    }

    private function isCurrentlyValid(PatientPayerProfile $profile): bool
    {
        return (! $profile->valid_from || $profile->valid_from->lte(today()))
            && (! $profile->valid_to || $profile->valid_to->gte(today()));
    }
}
