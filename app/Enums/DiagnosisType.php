<?php

namespace App\Enums;

enum DiagnosisType: string
{
    case Provisional = 'provisional';
    case Differential = 'differential';
    case Final = 'final';
    case Confirmed = 'confirmed';
    case RuleOut = 'rule_out';
}
