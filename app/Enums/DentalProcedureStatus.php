<?php

namespace App\Enums;

enum DentalProcedureStatus: string
{
    case Planned = 'planned';
    case AwaitingPayment = 'awaiting_payment';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case EnteredInError = 'entered_in_error';
}
