<?php

namespace App\Enums;

enum DiagnosisStatus: string
{
    case Active = 'active';
    case Resolved = 'resolved';
    case Chronic = 'chronic';
    case RuledOut = 'ruled_out';
    case EnteredInError = 'entered_in_error';
}
