<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Probation = 'probation';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Resigned = 'resigned';
    case Terminated = 'terminated';
    case Retired = 'retired';
    case Deceased = 'deceased';
    case Inactive = 'inactive';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active, self::Probation => 'success',
            self::Pending, self::OnLeave => 'warning',
            self::Suspended, self::Terminated, self::Deceased, self::Inactive => 'danger',
            self::Resigned, self::Retired => 'info',
        };
    }
}
