<?php

namespace App\Enums;

enum PayerType: string
{
    case Cash = 'cash';
    case Insurance = 'insurance';
    case Corporate = 'corporate';
    case Exempted = 'exempted';
    case Staff = 'staff';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
