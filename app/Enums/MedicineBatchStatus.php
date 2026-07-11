<?php

namespace App\Enums;

enum MedicineBatchStatus: string
{
    case Active = 'active';
    case Quarantined = 'quarantined';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
    case Recalled = 'recalled';
    case Damaged = 'damaged';
    case Blocked = 'blocked';
}
