<?php

namespace App\Enums;

enum ToothSurface: string
{
    case Mesial = 'mesial';
    case Distal = 'distal';
    case Occlusal = 'occlusal';
    case Buccal = 'buccal';
    case Lingual = 'lingual';
    case Incisal = 'incisal';
    case Labial = 'labial';
}
