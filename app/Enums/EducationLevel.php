<?php

namespace App\Enums;

enum EducationLevel: string
{
    case Certificate = 'certificate';
    case Diploma = 'diploma';
    case AdvancedDiploma = 'advanced_diploma';
    case Bachelor = 'bachelor';
    case PostgraduateDiploma = 'postgraduate_diploma';
    case Masters = 'masters';
    case Doctorate = 'doctorate';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
