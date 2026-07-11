<?php

namespace App\Enums;

enum EmploymentCategory: string
{
    case Permanent = 'permanent';
    case Contract = 'contract';
    case Temporary = 'temporary';
    case Volunteer = 'volunteer';
    case Intern = 'intern';
    case Consultant = 'consultant';
    case PartTime = 'part_time';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
