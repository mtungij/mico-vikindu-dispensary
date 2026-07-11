<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Inasubiri',
            self::Active => 'Active',
            self::Suspended => 'Imesimamishwa',
            self::Inactive => 'Haifanyi kazi',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Pending => 'warning',
            self::Suspended, self::Inactive => 'danger',
        };
    }
}
