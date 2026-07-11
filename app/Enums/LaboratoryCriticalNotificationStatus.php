<?php

namespace App\Enums;

enum LaboratoryCriticalNotificationStatus: string
{
    case Pending = 'pending';
    case Notified = 'notified';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
}
