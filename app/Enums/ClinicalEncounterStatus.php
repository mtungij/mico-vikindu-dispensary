<?php

namespace App\Enums;

enum ClinicalEncounterStatus: string
{
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case AwaitingLab = 'awaiting_lab';
    case AwaitingResults = 'awaiting_results';
    case AwaitingPharmacy = 'awaiting_pharmacy';
    case AwaitingProcedure = 'awaiting_procedure';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Referred = 'referred';
}
