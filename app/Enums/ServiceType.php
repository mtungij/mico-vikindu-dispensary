<?php

namespace App\Enums;

enum ServiceType: string
{
    case Registration = 'registration';
    case Consultation = 'consultation';
    case LaboratoryTest = 'laboratory_test';
    case Medicine = 'medicine';
    case DentalService = 'dental_service';
    case RchService = 'rch_service';
    case BedRest = 'bed_rest';
    case Procedure = 'procedure';
    case NursingService = 'nursing_service';
    case AdministrativeService = 'administrative_service';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
