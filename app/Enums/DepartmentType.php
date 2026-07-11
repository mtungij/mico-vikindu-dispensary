<?php

namespace App\Enums;

enum DepartmentType: string
{
    case Clinical = 'clinical';
    case Diagnostic = 'diagnostic';
    case Pharmacy = 'pharmacy';
    case Administrative = 'administrative';
    case Financial = 'financial';
    case Support = 'support';
    case MaternalChildHealth = 'maternal_child_health';
    case Dental = 'dental';
    case Observation = 'observation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Clinical => 'Clinical',
            self::Diagnostic => 'Diagnostic',
            self::Pharmacy => 'Pharmacy',
            self::Administrative => 'Administrative',
            self::Financial => 'Financial',
            self::Support => 'Support',
            self::MaternalChildHealth => 'Maternal & Child Health',
            self::Dental => 'Dental',
            self::Observation => 'Observation',
            self::Other => 'Nyingine',
        };
    }
}
