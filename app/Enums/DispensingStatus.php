<?php

namespace App\Enums;

enum DispensingStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case PartiallyDispensed = 'partially_dispensed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';
}
