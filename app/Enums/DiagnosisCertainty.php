<?php

namespace App\Enums;

enum DiagnosisCertainty: string
{
    case Suspected = 'suspected';
    case Probable = 'probable';
    case Confirmed = 'confirmed';
}
