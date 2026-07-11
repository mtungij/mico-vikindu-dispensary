<?php

namespace App\Enums;

enum ClinicalPaymentStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Paid = 'paid';
    case Covered = 'covered';
    case Waived = 'waived';
}
