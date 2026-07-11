<?php

namespace App\Enums;

enum ConsciousnessLevel: string
{
    case Alert = 'alert';
    case RespondsToVoice = 'responds_to_voice';
    case RespondsToPain = 'responds_to_pain';
    case Unresponsive = 'unresponsive';
    case Confused = 'confused';
}
