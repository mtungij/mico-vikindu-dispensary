<?php

namespace App\Enums;

enum DentalLabOrderStatus: string
{
    case Draft = 'draft';
    case Prepared = 'prepared';
    case Sent = 'sent';
    case InProgress = 'in_progress';
    case Received = 'received';
    case Fitted = 'fitted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
