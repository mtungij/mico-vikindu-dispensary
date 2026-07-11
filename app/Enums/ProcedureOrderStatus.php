<?php

namespace App\Enums;

enum ProcedureOrderStatus: string
{
    case Ordered = 'ordered';
    case AwaitingPayment = 'awaiting_payment';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
