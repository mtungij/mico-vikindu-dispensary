<?php

namespace App\Enums;

enum PregnancyStatus: string
{
    case NotApplicable = 'not_applicable';
    case NotPregnant = 'not_pregnant';
    case Pregnant = 'pregnant';
    case Suspected = 'suspected';
    case Unknown = 'unknown';
}
