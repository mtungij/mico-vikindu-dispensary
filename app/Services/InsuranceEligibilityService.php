<?php

namespace App\Services;

use App\Models\InsuranceVerification;
use App\Models\PatientInsuranceMembership;
use App\Models\Visit;

class InsuranceEligibilityService
{
    public function validateMembership(PatientInsuranceMembership $membership): array
    {
        $errors = [];
        if (! $membership->is_active) $errors[] = 'Membership is inactive.';
        if ($membership->verification_status !== 'verified' && $membership->verification_status !== 'manual_override') $errors[] = 'Membership is not verified.';
        if ($membership->valid_from && $membership->valid_from->isFuture()) $errors[] = 'Membership is not yet effective.';
        if ($membership->valid_to && $membership->valid_to->isPast()) $errors[] = 'Membership has expired.';

        return ['eligible' => $errors === [], 'errors' => $errors, 'status' => $membership->verification_status];
    }

    public function buildEligibilitySummary(PatientInsuranceMembership $membership, ?Visit $visit = null): array
    {
        $base = $this->validateMembership($membership);

        return [
            'eligible' => $base['eligible'],
            'membership_status' => $base['status'],
            'scheme' => $membership->scheme?->name,
            'benefit_package' => $membership->benefitPackage?->name,
            'patient_contribution' => 0,
            'payer_contribution' => null,
            'authorization_required' => (bool) ($membership->scheme?->requires_pre_authorization || $membership->provider?->requires_pre_authorization),
            'referral_required' => (bool) ($membership->scheme?->requires_referral || $membership->provider?->requires_referral),
            'warnings' => [],
            'blocking_errors' => $base['errors'],
            'visit_id' => $visit?->id,
        ];
    }

    public function recordVerification(PatientInsuranceMembership $membership, string $status = 'verified', string $method = 'manual', ?string $reason = null): InsuranceVerification
    {
        $membership->update(['verification_status' => $status, 'last_verified_at' => now(), 'verification_method' => $method, 'verification_notes' => $reason]);

        return InsuranceVerification::query()->create([
            'facility_id' => $membership->facility_id,
            'patient_id' => $membership->patient_id,
            'membership_id' => $membership->id,
            'verification_type' => 'membership',
            'verification_method' => $method,
            'status' => $status === 'manual_override' ? 'overridden' : $status,
            'verified_at' => now(),
            'verified_by' => auth()->id() ?? $membership->created_by,
            'override_reason' => $reason,
        ]);
    }
}
