<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\DentalEncounter;
use App\Models\Visit;

class DentalWorkflowService
{
    public function __construct(private readonly WorkflowService $workflow, private readonly DentalEncounterService $encounters) {}

    public function startDentalEncounter(Visit $visit, $actor, ?string $overrideReason = null): DentalEncounter
    {
        return $this->encounters->start($visit, $actor, $overrideReason);
    }

    public function sendToBilling(DentalEncounter $encounter, $actor, string $reason = 'Dental payment required'): void
    {
        $this->transferByCode($encounter, 'BIL', VisitStatus::AwaitingPayment, $actor, $reason);
    }

    public function sendToProcedure(DentalEncounter $encounter, $actor, string $reason = 'Dental procedure required'): void
    {
        $this->transferByCode($encounter, 'DEN', VisitStatus::Waiting, $actor, $reason);
    }

    public function sendToPharmacy(DentalEncounter $encounter, $actor, string $reason = 'Dental prescription created'): void
    {
        $this->transferByCode($encounter, 'PHA', VisitStatus::AwaitingPharmacy, $actor, $reason, true);
    }

    public function sendToObservation(DentalEncounter $encounter, $actor, string $reason = 'Post dental procedure observation'): void
    {
        $this->transferByCode($encounter, 'BED', VisitStatus::AwaitingBed, $actor, $reason, true);
    }

    public function scheduleFollowUp(DentalEncounter $encounter, $actor): DentalEncounter
    {
        $encounter->update(['status' => 'follow_up_required', 'updated_by' => $actor->id]);
        return $encounter->refresh();
    }

    public function referPatient(DentalEncounter $encounter, $actor): DentalEncounter
    {
        $encounter->update(['status' => 'referred', 'updated_by' => $actor->id]);
        $this->workflow->updateVisitStatus($encounter->visit, VisitStatus::Referred, $actor);
        return $encounter->refresh();
    }

    public function completeEncounter(DentalEncounter $encounter, $actor): DentalEncounter
    {
        return $this->encounters->complete($encounter, $actor);
    }

    private function transferByCode(DentalEncounter $encounter, string $code, VisitStatus $status, $actor, string $reason, bool $override = false): void
    {
        $department = Department::query()->where('facility_id', $encounter->facility_id)->where('code', $code)->first();
        if ($department) {
            $this->workflow->transferPatient($encounter->visit, $department, $reason, $actor, $status, $override, $override ? $actor : null);
        }
    }
}
