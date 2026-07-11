<?php

namespace App\Enums;

enum ClinicalEncounterType: string
{
    case Opd = 'opd';
    case Emergency = 'emergency';
    case Dental = 'dental';
    case Rch = 'rch';
    case BedRestReview = 'bed_rest_review';
    case FollowUp = 'follow_up';
    case ConsultationOnly = 'consultation_only';
    case Other = 'other';
}
