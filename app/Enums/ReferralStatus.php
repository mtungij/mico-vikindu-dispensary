<?php

namespace App\Enums;

enum ReferralStatus: string
{
    case Prepared = 'prepared';
    case Sent = 'sent';
    case Received = 'received';
    case FeedbackReceived = 'feedback_received';
    case Cancelled = 'cancelled';
}
