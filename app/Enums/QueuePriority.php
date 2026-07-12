<?php

namespace App\Enums;

enum QueuePriority: string
{
    case Emergency = 'emergency';
    case Urgent = 'urgent';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function weight(): int
    {
        return match ($this) {
            self::Emergency => 1,
            self::Urgent => 2,
            self::High => 3,
            self::Normal => 4,
            self::Low => 5,
        };
    }
}
