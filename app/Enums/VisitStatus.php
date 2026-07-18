<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Registered = 'registered';
    case AwaitingPayment = 'awaiting_payment';
    case AwaitingTriage = 'awaiting_triage';
    case AwaitingDepartment = 'awaiting_department';
    case InQueue = 'in_queue';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Called = 'called';
    case Serving = 'serving';
    case InConsultation = 'in_consultation';
    case AwaitingLab = 'awaiting_lab';
    case AwaitingSample = 'awaiting_sample';
    case Processing = 'processing';
    case AwaitingVerification = 'awaiting_verification';
    case ResultsReady = 'results_ready';
    case AwaitingDoctorReview = 'awaiting_doctor_review';
    case AwaitingResults = 'awaiting_results';
    case AwaitingPharmacy = 'awaiting_pharmacy';
    case AwaitingBed = 'awaiting_bed';
    case UnderObservation = 'under_observation';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Referred = 'referred';
    case Discharged = 'discharged';

    public function badge(): string
    {
        return match ($this) {
            self::Completed, self::Discharged => 'success',
            self::Cancelled => 'danger',
            self::AwaitingPayment, self::AwaitingTriage, self::AwaitingDepartment, self::InQueue, self::Waiting, self::Called => 'warning',
            self::InProgress, self::Serving, self::InConsultation, self::Processing, self::UnderObservation => 'info',
            default => 'info',
        };
    }
}
