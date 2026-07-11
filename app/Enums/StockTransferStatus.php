<?php

namespace App\Enums;

enum StockTransferStatus: string
{
    case Draft = 'draft';
    case Requested = 'requested';
    case Approved = 'approved';
    case Dispatched = 'dispatched';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
