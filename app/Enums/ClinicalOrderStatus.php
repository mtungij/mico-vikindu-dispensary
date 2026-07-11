<?php

namespace App\Enums;

enum ClinicalOrderStatus: string
{
    case Draft = 'draft';
    case AwaitingPayment = 'awaiting_payment';
    case Ordered = 'ordered';
    case SamplePending = 'sample_pending';
    case Processing = 'processing';
    case ResultReady = 'result_ready';
    case Verified = 'verified';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
