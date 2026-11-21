<?php

namespace App\Enums;

enum RchService: string
{
    case ChildMonitoring = 'CGM';
    case Anc = 'RCH-ANC';
    case Pnc = 'RCH-PNC';
    case FamilyPlanning = 'FPCON';
    case Ultrasound = 'RCH-US';

    public function label(): string
    {
        return match ($this) {
            self::ChildMonitoring => 'Child Monitoring',
            self::Anc => 'ANC Consultation',
            self::Pnc => 'PNC Consultation',
            self::FamilyPlanning => 'Family Planning',
            self::Ultrasound => 'Ultrasound',
        };
    }

    public function catalogueName(): string
    {
        return $this === self::Ultrasound ? 'RCH Ultrasound' : $this->label();
    }

    public function defaultPrice(): ?int
    {
        return match ($this) {
            self::ChildMonitoring => 500,
            self::Anc, self::Pnc => 1000,
            self::FamilyPlanning => null,
            self::Ultrasound => 15000,
        };
    }

    public function encounterType(): string
    {
        return match ($this) {
            self::ChildMonitoring => 'child_growth',
            self::Anc => 'anc',
            self::Pnc => 'pnc',
            self::FamilyPlanning => 'family_planning',
            self::Ultrasound => 'ultrasound',
        };
    }

    public function visitPermission(): ?string
    {
        return match ($this) {
            self::Anc => 'rch.anc.record-visit',
            self::Pnc => 'rch.pnc.record-visit',
            self::FamilyPlanning => 'rch.family-planning.record-visit',
            self::ChildMonitoring => 'rch.growth.record',
            self::Ultrasound => null,
        };
    }

    public function visitRoute(): ?string
    {
        return match ($this) {
            self::Anc => 'rch.visits.anc',
            self::Pnc => 'rch.visits.pnc',
            self::FamilyPlanning => 'rch.visits.family-planning',
            self::ChildMonitoring => 'rch.visits.child-growth',
            self::Ultrasound => null,
        };
    }

    public function serviceType(): ServiceType
    {
        return $this === self::Ultrasound ? ServiceType::Procedure : ServiceType::RchService;
    }
}
