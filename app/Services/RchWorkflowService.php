<?php

namespace App\Services;

use App\Enums\ClinicalEncounterType;
use App\Enums\VisitStatus;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\RchEncounter;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class RchWorkflowService
{
    public function __construct(private readonly RchEncounterNumberService $numbers, private readonly WorkflowService $workflow, private readonly AppointmentService $appointments) {}

    public function startEncounter(Visit $visit, string $type, $actor): RchEncounter
    {
        return DB::transaction(function () use ($visit, $type, $actor): RchEncounter {
            $facilityId = currentFacility()?->id ?? $visit->facility_id;
            $clinical = ClinicalEncounter::query()->firstOrCreate([
                'facility_id' => $facilityId,
                'visit_id' => $visit->id,
                'department_id' => $visit->current_department_id ?? $visit->destination_department_id,
                'status' => 'in_progress',
            ], [
                'patient_id' => $visit->patient_id,
                'encounter_type' => ClinicalEncounterType::Rch,
                'encounter_number' => app(ClinicalEncounterNumberService::class)->next($facilityId),
                'provider_user_id' => $actor->id,
                'started_at' => now(),
                'created_by' => $actor->id,
            ]);

            $encounter = RchEncounter::query()->firstOrCreate([
                'facility_id' => $facilityId,
                'visit_id' => $visit->id,
                'clinical_encounter_id' => $clinical->id,
            ], [
                'patient_id' => $visit->patient_id,
                'encounter_type' => $type,
                'encounter_number' => $this->numbers->next($facilityId),
                'provider_user_id' => $actor->id,
                'status' => 'in_progress',
                'started_at' => now(),
                'created_by' => $actor->id,
            ]);

            $visit->update(['visit_status' => VisitStatus::InConsultation, 'current_assigned_user_id' => $actor->id, 'updated_by' => $actor->id]);
            return $encounter;
        });
    }

    public function sendToBilling(Visit $visit, $actor): void { $visit->update(['visit_status' => VisitStatus::AwaitingPayment, 'updated_by' => $actor->id]); }
    public function sendToLaboratory(Visit $visit, Department $lab, $actor): void { $this->workflow->transferPatient($visit, $lab, 'RCH laboratory order', $actor, VisitStatus::AwaitingLab); }
    public function returnFromLaboratory(Visit $visit, Department $rch, $actor): void { $this->workflow->transferPatient($visit, $rch, 'Laboratory results ready for RCH review', $actor, VisitStatus::AwaitingDepartment); }
    public function sendToPharmacy(Visit $visit, Department $pharmacy, $actor): void { $this->workflow->transferPatient($visit, $pharmacy, 'RCH prescription', $actor, VisitStatus::AwaitingPharmacy); }
    public function sendToObservation(Visit $visit, Department $observation, $actor): void { $this->workflow->transferPatient($visit, $observation, 'RCH observation referral', $actor, VisitStatus::Waiting); }
    public function referPatient(RchEncounter $encounter, string $reason, $actor): RchEncounter { $encounter->update(['status' => 'referred', 'clinical_summary' => $reason, 'updated_by' => $actor->id]); return $encounter->refresh(); }
    public function completeEncounter(RchEncounter $encounter, $actor): RchEncounter { $encounter->update(['status' => 'completed', 'completed_at' => now(), 'signed_off_by' => $actor->id, 'signed_off_at' => now(), 'updated_by' => $actor->id]); return $encounter->refresh(); }
    public function scheduleFollowUp(RchEncounter $encounter, array $data, $actor) { return $this->appointments->create(array_merge($data, ['patient_id' => $encounter->patient_id, 'visit_id' => $encounter->visit_id, 'clinical_encounter_id' => $encounter->clinical_encounter_id]), $actor); }
}
