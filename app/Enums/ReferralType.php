<?php

namespace App\Enums;

enum ReferralType: string
{
    case External = 'external';
    case Internal = 'internal';
    case EmergencyTransfer = 'emergency_transfer';
    case SpecialistReview = 'specialist_review';
}
