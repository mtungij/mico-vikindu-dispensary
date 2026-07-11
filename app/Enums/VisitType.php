<?php

namespace App\Enums;

enum VisitType: string
{
    case NewPatient = 'new_patient';
    case ReturningPatient = 'returning_patient';
    case FollowUp = 'follow_up';
    case Emergency = 'emergency';
    case Dental = 'dental';
    case Rch = 'rch';
    case LaboratoryOnly = 'laboratory_only';
    case PharmacyOnly = 'pharmacy_only';
    case BedRest = 'bed_rest';
    case Referral = 'referral';
    case Other = 'other';
}
