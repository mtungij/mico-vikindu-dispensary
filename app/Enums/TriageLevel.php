<?php

namespace App\Enums;

enum TriageLevel: string
{
    case Emergency = 'emergency';
    case Urgent = 'urgent';
    case Priority = 'priority';
    case Routine = 'routine';

    public function label(): string
    {
        return match ($this) {
            self::Emergency => 'Dharura',
            self::Urgent => 'Haraka',
            self::Priority => 'Kipaumbele',
            self::Routine => 'Kawaida',
        };
    }
}
