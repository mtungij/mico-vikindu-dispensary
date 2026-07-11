<?php

namespace App\Enums;

enum PharmacyReturnStatus: string
{
    case Draft = 'draft';
    case Received = 'received';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}
