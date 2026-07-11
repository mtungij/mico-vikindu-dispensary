<?php

namespace App\Enums;

enum FacilityType: string
{
    case Dispensary = 'dispensary';
    case HealthCentre = 'health_centre';
    case Clinic = 'clinic';
    case DentalClinic = 'dental_clinic';
    case MaternityClinic = 'maternity_clinic';
    case DiagnosticCentre = 'diagnostic_centre';
    case Hospital = 'hospital';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Dispensary => 'Dispensary',
            self::HealthCentre => 'Health Centre',
            self::Clinic => 'Clinic',
            self::DentalClinic => 'Dental Clinic',
            self::MaternityClinic => 'Maternity Clinic',
            self::DiagnosticCentre => 'Diagnostic Centre',
            self::Hospital => 'Hospital',
            self::Other => 'Nyingine',
        };
    }
}
