<?php

namespace App\Enums;

enum DentalEncounterStatus: string
{
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case AwaitingImaging = 'awaiting_imaging';
    case AwaitingPayment = 'awaiting_payment';
    case AwaitingProcedure = 'awaiting_procedure';
    case AwaitingDentalLab = 'awaiting_dental_lab';
    case FollowUpRequired = 'follow_up_required';
    case Completed = 'completed';
    case Referred = 'referred';
    case Cancelled = 'cancelled';
}
