<?php

namespace App\Enums;

enum AppointmentType: string
{
    case GeneralConsultation = 'general_consultation';
    case FollowUpVisit = 'follow_up_visit';
    case Review = 'review';
    case Laboratory = 'laboratory';
    case Dental = 'dental';
    case Pnc = 'pnc';
    case Anc = 'anc';
    case FamilyPlanning = 'family_planning';
    case ChildClinic = 'child_clinic';
    case HomeCare = 'home_care';
    case Teleconsultation = 'teleconsultation';
    case OpdFollowUp = 'opd_follow_up';
    case DentalReview = 'dental_review';
    case ChildGrowth = 'child_growth';
    case Immunization = 'immunization';
    case ObservationReview = 'observation_review';
    case LabReview = 'lab_review';
    case Dressing = 'dressing';
    case Procedure = 'procedure';
    case Other = 'other';
}
