<?php

namespace App\Enums;

enum ClinicalAlertStatus: string
{
    case Active = 'active';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
