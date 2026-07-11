<?php

namespace App\Enums;

enum VisitPriority: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';
    case Emergency = 'emergency';
}
