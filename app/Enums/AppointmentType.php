<?php

namespace App\Enums;

enum AppointmentType: string
{
    case OpdFollowUp = 'opd_follow_up';
    case DentalReview = 'dental_review';
    case Anc = 'anc';
    case FamilyPlanning = 'family_planning';
    case ChildGrowth = 'child_growth';
    case Immunization = 'immunization';
    case LabReview = 'lab_review';
    case Dressing = 'dressing';
    case Procedure = 'procedure';
    case Other = 'other';
}
