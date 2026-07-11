<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Models\Patient;
use App\Models\PatientPayerProfile;
use Illuminate\Validation\ValidationException;

class PatientPayerService
{
    public function createProfile(Patient $patient, array $data, $actor): PatientPayerProfile
    {
        $payer = PayerType::from($data['payer_type']);
        if ($payer === PayerType::Insurance && blank($data['insurance_provider_id'] ?? null)) {
            throw ValidationException::withMessages(['insurance_provider_id' => 'Bima inahitaji provider.']);
        }
        if ($payer === PayerType::Corporate && blank($data['corporate_account_id'] ?? null)) {
            throw ValidationException::withMessages(['corporate_account_id' => 'Corporate inahitaji account.']);
        }
        if (($data['is_primary'] ?? true) === true) {
            $patient->payerProfiles()->update(['is_primary' => false]);
        }

        return $patient->payerProfiles()->create([
            ...$data,
            'facility_id' => $patient->facility_id,
            'is_primary' => $data['is_primary'] ?? true,
            'coverage_status' => $data['coverage_status'] ?? ($payer === PayerType::Cash ? 'active' : 'pending_verification'),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }
}
