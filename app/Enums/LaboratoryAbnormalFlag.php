<?php

namespace App\Enums;

enum LaboratoryAbnormalFlag: string
{
    case Normal = 'normal';
    case Low = 'low';
    case High = 'high';
    case CriticalLow = 'critical_low';
    case CriticalHigh = 'critical_high';
    case Abnormal = 'abnormal';
    case Critical = 'critical';
    case Indeterminate = 'indeterminate';
}
