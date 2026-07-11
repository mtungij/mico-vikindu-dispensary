<?php

namespace App\Enums;

enum OutsourcedLaboratoryStatus: string
{
    case Prepared = 'prepared';
    case Sent = 'sent';
    case Received = 'received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
