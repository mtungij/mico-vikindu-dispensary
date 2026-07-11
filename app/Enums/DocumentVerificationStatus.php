<?php

namespace App\Enums;

enum DocumentVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Inasubiri',
            self::Verified => 'Imethibitishwa',
            self::Rejected => 'Imekataliwa',
            self::Expired => 'Imeisha muda',
        };
    }
}
