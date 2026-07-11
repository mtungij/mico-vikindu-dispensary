<?php

namespace App\Enums;

enum LaboratoryResultStatus: string
{
    case Draft = 'draft';
    case Entered = 'entered';
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case Released = 'released';
    case Amended = 'amended';
    case Cancelled = 'cancelled';
    case EnteredInError = 'entered_in_error';
}
