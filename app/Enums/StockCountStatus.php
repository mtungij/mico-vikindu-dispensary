<?php

namespace App\Enums;

enum StockCountStatus: string
{
    case Draft = 'draft';
    case Counting = 'counting';
    case Completed = 'completed';
    case Verified = 'verified';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}
