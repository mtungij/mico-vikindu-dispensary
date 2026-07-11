<?php

namespace App\Enums;

enum PurchaseReceiptStatus: string
{
    case Draft = 'draft';
    case Received = 'received';
    case Verified = 'verified';
    case Cancelled = 'cancelled';
}
