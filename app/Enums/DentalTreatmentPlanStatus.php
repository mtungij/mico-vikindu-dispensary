<?php

namespace App\Enums;

enum DentalTreatmentPlanStatus: string
{
    case Draft = 'draft';
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
}
