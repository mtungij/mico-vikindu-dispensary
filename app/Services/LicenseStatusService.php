<?php

namespace App\Services;

use App\Enums\ProfessionalLicenseStatus;
use App\Models\StaffProfessionalLicense;

class LicenseStatusService
{
    public function calculate(?string $expiryDate): ProfessionalLicenseStatus
    {
        if ($expiryDate === null) {
            return ProfessionalLicenseStatus::Unknown;
        }

        $expiry = now()->parse($expiryDate)->startOfDay();

        if ($expiry->isPast()) {
            return ProfessionalLicenseStatus::Expired;
        }

        if ($expiry->diffInDays(now(), true) <= 30) {
            return ProfessionalLicenseStatus::Expiring;
        }

        return ProfessionalLicenseStatus::Active;
    }

    public function refreshAll(): int
    {
        $count = 0;

        StaffProfessionalLicense::query()->whereNotNull('expiry_date')->chunkById(100, function ($licenses) use (&$count): void {
            foreach ($licenses as $license) {
                $license->update(['status' => $this->calculate($license->expiry_date?->toDateString())]);
                $count++;
            }
        });

        return $count;
    }
}
