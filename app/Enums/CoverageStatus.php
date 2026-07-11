<?php

namespace App\Enums;

enum CoverageStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';
    case PendingVerification = 'pending_verification';
    case Rejected = 'rejected';
    case Unknown = 'unknown';
}
