<?php

namespace App\Enums;

enum LaboratorySampleStatus: string
{
    case Pending = 'pending';
    case Collected = 'collected';
    case Received = 'received';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Processing = 'processing';
    case Completed = 'completed';
    case Disposed = 'disposed';
    case Lost = 'lost';
    case RecollectionRequired = 'recollection_required';
    case Cancelled = 'cancelled';
}
