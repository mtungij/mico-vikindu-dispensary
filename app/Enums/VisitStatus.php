<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Registered = 'registered';
    case AwaitingPayment = 'awaiting_payment';
    case AwaitingTriage = 'awaiting_triage';
    case AwaitingDepartment = 'awaiting_department';
    case InQueue = 'in_queue';
    case InConsultation = 'in_consultation';
    case AwaitingLab = 'awaiting_lab';
    case AwaitingResults = 'awaiting_results';
    case AwaitingPharmacy = 'awaiting_pharmacy';
    case AwaitingBed = 'awaiting_bed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Referred = 'referred';
    case Discharged = 'discharged';

    public function badge(): string
    {
        return match ($this) {
            self::Completed, self::Discharged => 'success',
            self::Cancelled => 'danger',
            self::AwaitingPayment, self::AwaitingTriage, self::AwaitingDepartment, self::InQueue => 'warning',
            default => 'info',
        };
    }
}
