<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
    case PreferNotToSay = 'prefer_not_to_say';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Mwanaume',
            self::Female => 'Mwanamke',
            self::Other => 'Nyingine',
            self::PreferNotToSay => 'Sipendi kusema',
        };
    }
}
