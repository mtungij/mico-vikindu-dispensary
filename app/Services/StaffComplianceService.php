<?php

namespace App\Services;

use App\Models\StaffProfile;

class StaffComplianceService
{
    public function getRequiredDocumentsForJobTitle(): array
    {
        return ['nida', 'curriculum_vitae', 'employment_contract'];
    }

    public function hasRequiredLicense(StaffProfile $staffProfile): bool
    {
        if (! $staffProfile->employmentRecord?->jobTitle?->requires_professional_license) {
            return true;
        }

        return $staffProfile->professionalLicenses()->where('status', 'active')->exists();
    }

    public function getLicenseStatus(StaffProfile $staffProfile): string
    {
        if (! $this->hasRequiredLicense($staffProfile)) {
            return 'missing';
        }

        if ($staffProfile->professionalLicenses()->where('status', 'expired')->exists()) {
            return 'expired';
        }

        if ($staffProfile->professionalLicenses()->where('status', 'expiring')->exists()) {
            return 'expiring';
        }

        return 'ok';
    }

    public function getMissingDocuments(StaffProfile $staffProfile): array
    {
        $existing = $staffProfile->documents()->pluck('document_type')->map(fn ($type) => is_string($type) ? $type : $type->value)->all();

        return array_values(array_diff($this->getRequiredDocumentsForJobTitle(), $existing));
    }

    public function getProfileCompletionPercentage(StaffProfile $staffProfile): int
    {
        $score = 0;
        $score += $staffProfile->first_name && $staffProfile->last_name && $staffProfile->primary_phone ? 20 : 0;
        $score += $staffProfile->employmentRecord?->job_title_id && $staffProfile->employmentRecord?->primary_department_id ? 20 : 0;
        $score += $staffProfile->user?->roles()->exists() ? 15 : 0;
        $score += $staffProfile->educationRecords()->exists() ? 15 : 0;
        $score += $this->hasRequiredLicense($staffProfile) ? 15 : 0;
        $score += $staffProfile->documents()->exists() ? 10 : 0;
        $score += $staffProfile->emergencyContacts()->exists() ? 5 : 0;

        return min(100, $score);
    }

    public function getComplianceWarnings(StaffProfile $staffProfile): array
    {
        return array_values(array_filter([
            $staffProfile->passport_photo_path ? null : 'Hakuna picha ya passport.',
            $staffProfile->educationRecords()->exists() ? null : 'Hakuna rekodi ya elimu.',
            $this->hasRequiredLicense($staffProfile) ? null : 'Mtumishi hana leseni inayohitajika kwa cheo hiki.',
            $staffProfile->emergencyContacts()->exists() ? null : 'Hakuna mtu wa dharura.',
            $staffProfile->documents()->exists() ? null : 'Hakuna nyaraka zilizopakiwa.',
        ]));
    }

    public function getExpiredDocuments(StaffProfile $staffProfile)
    {
        return $staffProfile->documents()->whereDate('expiry_date', '<', today())->get();
    }

    public function getExpiringDocuments(StaffProfile $staffProfile)
    {
        return $staffProfile->documents()->whereBetween('expiry_date', [today(), today()->addDays(30)])->get();
    }
}
