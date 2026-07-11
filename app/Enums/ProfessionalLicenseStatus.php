<?php

namespace App\Enums;

enum ProfessionalLicenseStatus: string
{
    case Active = 'active';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case PendingRenewal = 'pending_renewal';
    case Unknown = 'unknown';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Expiring, self::PendingRenewal => 'warning',
            self::Expired, self::Suspended, self::Revoked => 'danger',
            self::Unknown => 'info',
        };
    }
}
