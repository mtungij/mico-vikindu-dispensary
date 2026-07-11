<?php

namespace App\Enums;

enum OwnershipType: string
{
    case Private = 'private';
    case Government = 'government';
    case FaithBased = 'faith_based';
    case Ngo = 'ngo';
    case Company = 'company';
    case Partnership = 'partnership';
    case Individual = 'individual';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Government => 'Government',
            self::FaithBased => 'Faith Based',
            self::Ngo => 'NGO',
            self::Company => 'Company',
            self::Partnership => 'Partnership',
            self::Individual => 'Individual',
            self::Other => 'Nyingine',
        };
    }
}
