<?php

namespace App\Enums;

enum LaboratoryResultType: string
{
    case Numeric = 'numeric';
    case Text = 'text';
    case LongText = 'long_text';
    case PositiveNegative = 'positive_negative';
    case ReactiveNonReactive = 'reactive_non_reactive';
    case DetectedNotDetected = 'detected_not_detected';
    case Choice = 'choice';
    case Boolean = 'boolean';
    case Date = 'date';
    case Time = 'time';
    case Image = 'image';
    case Composite = 'composite';
    case NoResult = 'no_result';
    case Other = 'other';
}
