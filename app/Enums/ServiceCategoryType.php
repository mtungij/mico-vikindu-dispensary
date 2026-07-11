<?php

namespace App\Enums;

enum ServiceCategoryType: string
{
    case Registration = 'registration';
    case Consultation = 'consultation';
    case Laboratory = 'laboratory';
    case Pharmacy = 'pharmacy';
    case Dental = 'dental';
    case Rch = 'rch';
    case BedRest = 'bed_rest';
    case Procedure = 'procedure';
    case Imaging = 'imaging';
    case Nursing = 'nursing';
    case Administrative = 'administrative';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
