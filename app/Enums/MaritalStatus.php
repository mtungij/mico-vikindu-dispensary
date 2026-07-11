<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';
    case Separated = 'separated';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Hajaoa/Hajaolewa',
            self::Married => 'Ameoa/Ameolewa',
            self::Divorced => 'Talaka',
            self::Widowed => 'Mjane/Mgane',
            self::Separated => 'Wametengana',
            self::Other => 'Nyingine',
        };
    }
}
