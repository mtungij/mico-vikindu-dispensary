<?php

namespace App\Enums;

enum DentalProcedureType: string
{
    case Preventive = 'preventive';
    case Restorative = 'restorative';
    case Endodontic = 'endodontic';
    case Orthodontic = 'orthodontic';
    case OralSurgery = 'oral_surgery';
    case Periodontal = 'periodontal';
    case Cosmetic = 'cosmetic';
    case Prosthodontic = 'prosthodontic';
    case Diagnostic = 'diagnostic';
    case Other = 'other';
}
