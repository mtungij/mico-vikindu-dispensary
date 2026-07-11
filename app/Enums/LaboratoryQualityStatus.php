<?php

namespace App\Enums;

enum LaboratoryQualityStatus: string
{
    case Acceptable = 'acceptable';
    case Hemolyzed = 'hemolyzed';
    case Clotted = 'clotted';
    case Insufficient = 'insufficient';
    case Contaminated = 'contaminated';
    case Leaking = 'leaking';
    case Mislabeled = 'mislabeled';
    case DelayedTransport = 'delayed_transport';
    case WrongContainer = 'wrong_container';
    case Other = 'other';
}
