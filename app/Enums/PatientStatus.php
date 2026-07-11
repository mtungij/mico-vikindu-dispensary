<?php

namespace App\Enums;

enum PatientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Deceased = 'deceased';
    case Archived = 'archived';
    case Blocked = 'blocked';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive, self::Archived => 'warning',
            self::Deceased, self::Blocked => 'danger',
        };
    }
}
