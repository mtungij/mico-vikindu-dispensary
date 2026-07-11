<?php

namespace App\Enums;

enum InsuranceProviderType: string
{
    case Nhif = 'nhif';
    case PrivateInsurance = 'private_insurance';
    case CommunityHealthFund = 'community_health_fund';
    case EmployerScheme = 'employer_scheme';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
