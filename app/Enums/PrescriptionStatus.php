<?php

namespace App\Enums;

enum PrescriptionStatus: string
{
    case Draft = 'draft';
    case Prescribed = 'prescribed';
    case AwaitingPayment = 'awaiting_payment';
    case PartiallyDispensed = 'partially_dispensed';
    case Dispensed = 'dispensed';
    case Cancelled = 'cancelled';
}
