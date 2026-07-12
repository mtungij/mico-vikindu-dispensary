<?php

namespace App\Services;

use App\Enums\ClinicalEncounterStatus;
use App\Enums\ClinicalEncounterType;
use App\Enums\DentalEncounterStatus;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\ClinicalEncounter;
use App\Models\DentalEncounter;
use App\Models\Department;
use App\Models\PatientQueue;
use App\Models\Visit;
use App\Models\VisitMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DentalEncounterService
{
    public function __construct(private readonly ClinicalEncounterNumberService $clinicalNumbers, private readonly DentalEncounterNumberService $numbers, private readonly DentalOdontogramService $odontogram, private readonly WorkflowService $workflow) {}

    public function start(Visit $visit, $actor, ?string $overrideReason = null): DentalEncounter
    {
        return DB::transaction(function () use ($visit, $actor, $overrideReason): DentalEncounter {
            $visit = Visit::query()->lockForUpdate()->with(['patient', 'invoice.items'])->findOrFail($visit->id);
            abort_unless($visit->facility_id === currentFacility()?->id, 404);
            if ($visit->visit_status === VisitStatus::AwaitingPayment && ! $actor->can('dental.override-payment')) {
                throw ValidationException::withMessages(['payment' => 'Malipo yanahitajika kabla ya dental consultation.']);
            }
            if ($visit->visit_status === VisitStatus::AwaitingPayment && blank($overrideReason)) {
                throw ValidationException::withMessages(['override_reason' => 'Sababu ya override inahitajika.']);
            }
            if ($existing = DentalEncounter::query()->where('visit_id', $visit->id)->whereNotIn('status', ['completed','cancelled','referred'])->first()) {
                if ((int) $existing->provider_user_id !== (int) $actor->id) {
                    throw ValidationException::withMessages(['visit' => 'Dental encounter tayari imeanza na provider mwingine.']);
                }
                return $existing;
            }
            $department = $visit->currentDepartment ?: Department::query()->where('facility_id', $visit->facility_id)->where('code', 'DEN')->firstOrFail();
            $clinical = ClinicalEncounter::query()->create([
                'facility_id'=>$visit->facility_id,'patient_id'=>$visit->patient_id,'visit_id'=>$visit->id,'department_id'=>$department->id,
                'encounter_type'=>ClinicalEncounterType::Dental,'encounter_number'=>$this->clinicalNumbers->next($visit->facility_id),
                'provider_user_id'=>$actor->id,'started_at'=>now(),'status'=>ClinicalEncounterStatus::InProgress,'created_by'=>$actor->id,
            ]);
            $dental = DentalEncounter::query()->create([
                'facility_id'=>$visit->facility_id,'patient_id'=>$visit->patient_id,'visit_id'=>$visit->id,'clinical_encounter_id'=>$clinical->id,
                'provider_user_id'=>$actor->id,'dental_encounter_number'=>$this->numbers->next($visit->facility_id),'status'=>DentalEncounterStatus::InProgress,
                'started_at'=>now(),'allergies_snapshot'=>$visit->patient->known_allergies,'current_medications_snapshot'=>$visit->patient->chronic_conditions,'created_by'=>$actor->id,
            ]);
            if ($queue = PatientQueue::query()->where('visit_id', $visit->id)->whereIn('queue_status', ['waiting','called'])->latest()->first()) $this->workflow->startService($queue, $actor);
            $this->workflow->updateCurrentDepartment($visit, $department, $actor, $queue ?? null);
            $this->workflow->updateVisitStatus($visit, VisitStatus::InConsultation, $actor, $queue ?? null);
            $this->workflow->createMovement($visit, $visit->currentDepartment, $department, $overrideReason, $actor, 'dental_encounter_started', filled($overrideReason), $actor);
            $this->odontogram->initializeDentition($dental, 'permanent', $actor);
            $this->audit($actor, $overrideReason ? 'dental_payment_override_used' : 'dental_encounter_started', $dental, ['visit_id'=>$visit->id]);
            return $dental->refresh();
        });
    }

    public function saveDraft(DentalEncounter $encounter, array $data, $actor): DentalEncounter
    {
        $this->ensureMutable($encounter, $actor);
        $encounter->update([...collect($data)->only(['complaint','dental_history','medical_history_review','oral_hygiene_history','previous_dental_treatment','tobacco_use','alcohol_use','brushing_frequency','flossing_frequency','dental_anxiety_level','extraoral_examination','intraoral_examination','periodontal_summary','occlusion_summary','radiographic_findings','clinical_summary','treatment_plan_summary'])->all(), 'updated_by'=>$actor->id]);
        return $encounter->refresh();
    }

    public function complete(DentalEncounter $encounter, $actor): DentalEncounter
    {
        return DB::transaction(function () use ($encounter, $actor): DentalEncounter {
            $encounter = DentalEncounter::query()->lockForUpdate()->findOrFail($encounter->id);
            $this->ensureMutable($encounter, $actor);
            if (! $encounter->diagnoses()->exists()) throw ValidationException::withMessages(['diagnosis'=>'Dental diagnosis inahitajika kabla ya completion.']);
            if (blank($encounter->clinical_summary) && blank($encounter->treatment_plan_summary)) throw ValidationException::withMessages(['summary'=>'Clinical summary au treatment plan inahitajika.']);
            $encounter->update(['status'=>DentalEncounterStatus::Completed,'completed_at'=>now(),'signed_off_by'=>$actor->id,'signed_off_at'=>now(),'updated_by'=>$actor->id]);
            $encounter->clinicalEncounter->update(['status'=>ClinicalEncounterStatus::Completed,'completed_at'=>now(),'signed_off_by'=>$actor->id,'signed_off_at'=>now(),'updated_by'=>$actor->id]);
            if ($queue = PatientQueue::query()->where('visit_id', $encounter->visit_id)->where('queue_status', 'serving')->latest()->first()) $this->workflow->completeQueue($queue, $actor);
            $this->workflow->completeVisit($encounter->visit, $actor, 'Dental encounter completed');
            $this->audit($actor, 'dental_encounter_completed', $encounter);
            return $encounter->refresh();
        });
    }

    public function ensureMutable(DentalEncounter $encounter, $actor): void
    {
        abort_unless($encounter->facility_id === currentFacility()?->id, 404);
        if ($encounter->isCompleted() && ! $actor->can('dental.complete-consultation')) throw ValidationException::withMessages(['encounter'=>'Completed dental encounter haiwezi kubadilishwa bila amendment permission.']);
    }

    private function audit($actor, string $event, DentalEncounter $encounter, array $values = []): void
    {
        ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>$event,'subject_type'=>DentalEncounter::class,'subject_id'=>$encounter->id,'new_values'=>$values,'ip_address'=>request()?->ip(),'user_agent'=>request()?->userAgent()]);
    }
}
