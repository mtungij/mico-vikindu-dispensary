<?php

namespace App\Enums;

enum DentalFindingStatus: string
{
    case Active = 'active';
    case Resolved = 'resolved';
    case Treated = 'treated';
    case EnteredInError = 'entered_in_error';
}
