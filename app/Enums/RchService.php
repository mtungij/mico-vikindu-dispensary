<?php

namespace App\Enums;

enum RchService: string
{
    case ChildMonitoring = 'CGM';
    case Anc = 'RCH-ANC';
    case Pnc = 'RCH-PNC';
    case Ultrasound = 'RCH-US';

    public function label(): string
    {
        return match ($this) {
            self::ChildMonitoring => 'Child Monitoring',
            self::Anc => 'ANC Consultation',
            self::Pnc => 'PNC Consultation',
            self::Ultrasound => 'Ultrasound',
        };
    }

    public function catalogueName(): string
    {
        return $this === self::Ultrasound ? 'RCH Ultrasound' : $this->label();
    }

    public function defaultPrice(): int
    {
        return match ($this) {
            self::ChildMonitoring => 500,
            self::Anc, self::Pnc => 1000,
            self::Ultrasound => 15000,
        };
    }

    public function encounterType(): string
    {
        return match ($this) {
            self::ChildMonitoring => 'child_growth',
            self::Anc => 'anc',
            self::Pnc => 'pnc',
            self::Ultrasound => 'ultrasound',
        };
    }

    public function serviceType(): ServiceType
    {
        return $this === self::Ultrasound ? ServiceType::Procedure : ServiceType::RchService;
    }
}
