<?php

namespace App\Enums;

enum ToothStatus: string
{
    case Present = 'present';
    case Missing = 'missing';
    case Extracted = 'extracted';
    case Unerupted = 'unerupted';
    case Impacted = 'impacted';
    case RetainedRoot = 'retained_root';
    case Implant = 'implant';
    case Pontic = 'pontic';
    case Other = 'other';
}
