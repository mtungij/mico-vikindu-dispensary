<?php

namespace App\Services;

use App\Enums\ClinicalEncounterStatus;
use App\Enums\ClinicalEncounterType;
use App\Enums\ClinicalOutcome;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\ClinicalComplaint;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalNoteAmendment;
use App\Models\PatientQueue;
use App\Models\PhysicalExamination;
use App\Models\Visit;
use App\Models\VisitMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClinicalEncounterService
{
    public function __construct(
        private readonly ClinicalEncounterNumberService $numbers,
        private readonly DiagnosisService $diagnoses,
        private readonly LaboratoryOrderService $laboratoryOrders,
        private readonly PrescriptionService $prescriptions,
        private readonly ProcedureOrderService $procedures,
        private readonly AppointmentService $appointments,
        private readonly ReferralService $referrals,
        private readonly WorkflowService $workflow,
    ) {}

    public function startEncounter(Visit $visit, $actor): ClinicalEncounter
    {
        return DB::transaction(function () use ($visit, $actor) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            if (in_array($visit->visit_status, [VisitStatus::Completed, VisitStatus::Cancelled, VisitStatus::Referred, VisitStatus::Discharged], true)) {
                throw ValidationException::withMessages(['visit' => 'Visit si active.']);
            }

            $existing = ClinicalEncounter::query()
                ->where('visit_id', $visit->id)
                ->where('department_id', $visit->current_department_id)
                ->where('provider_user_id', $actor->id)
                ->whereNotIn('status', [ClinicalEncounterStatus::Completed->value, ClinicalEncounterStatus::Cancelled->value, ClinicalEncounterStatus::Referred->value])
                ->first();
            if ($existing) {
                return $existing;
            }

            if (ClinicalEncounter::query()->where('visit_id', $visit->id)->where('department_id', $visit->current_department_id)->whereNotIn('status', [ClinicalEncounterStatus::Completed->value, ClinicalEncounterStatus::Cancelled->value, ClinicalEncounterStatus::Referred->value])->exists()) {
                throw ValidationException::withMessages(['visit' => 'Consultation tayari imeanza kwa department hii.']);
            }

            $queue = PatientQueue::query()->where('visit_id', $visit->id)->where('department_id', $visit->current_department_id)->whereIn('queue_status', ['waiting', 'called'])->lockForUpdate()->latest()->first();
            if ($queue) {
                $this->workflow->startService($queue, $actor);
            }

            $encounter = ClinicalEncounter::query()->create([
                'facility_id' => $visit->facility_id,
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'department_id' => $visit->current_department_id,
                'encounter_type' => ClinicalEncounterType::Opd,
                'encounter_number' => $this->numbers->next($visit->facility_id),
                'provider_user_id' => $actor->id,
                'started_at' => now(),
                'status' => ClinicalEncounterStatus::InProgress,
                'created_by' => $actor->id,
            ]);

            $this->workflow->updateVisitStatus($visit, VisitStatus::InConsultation, $actor, $queue);
            $this->workflow->createMovement($visit, $visit->currentDepartment, $visit->currentDepartment, 'Clinical encounter started', $actor, 'clinical_encounter_started');
            $this->audit($actor, 'clinical_encounter_started', $encounter);

            return $encounter;
        });
    }

    public function saveDraft(ClinicalEncounter $encounter, array $data, $actor): ClinicalEncounter
    {
        $this->ensureMutable($encounter, $actor);
        $allowed = collect($data)->only(['chief_complaint', 'history_of_presenting_illness', 'past_medical_history', 'surgical_history', 'medication_history', 'allergy_history', 'family_history', 'social_history', 'obstetric_history', 'gynecological_history', 'review_of_systems', 'physical_examination', 'clinical_summary', 'assessment_notes', 'treatment_plan', 'discharge_instructions', 'follow_up_required', 'follow_up_date', 'outcome'])->all();
        $encounter->update([...$allowed, 'updated_by' => $actor->id]);
        $this->audit($actor, 'clinical_encounter_draft_saved', $encounter, ['fields' => array_keys($allowed)]);
        return $encounter->refresh();
    }

    public function addComplaint(ClinicalEncounter $encounter, array $data, $actor): ClinicalComplaint
    {
        $this->ensureMutable($encounter, $actor);
        return DB::transaction(function () use ($encounter, $data, $actor) {
            if ($data['is_primary'] ?? false) {
                $encounter->complaints()->update(['is_primary' => false]);
            }
            return $encounter->complaints()->create([...$data, 'created_by' => $actor->id]);
        });
    }

    public function updateExamination(ClinicalEncounter $encounter, array $data, $actor): PhysicalExamination
    {
        $this->ensureMutable($encounter, $actor);
        return PhysicalExamination::query()->updateOrCreate([
            'clinical_encounter_id' => $encounter->id,
            'examination_system' => $data['examination_system'],
        ], [
            'findings' => $data['findings'] ?? null,
            'status' => $data['status'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    public function addDiagnosis(ClinicalEncounter $encounter, array $data, $actor) { $this->ensureMutable($encounter, $actor); return $this->diagnoses->addDiagnosis($encounter, $data, $actor); }
    public function addLabOrder(ClinicalEncounter $encounter, array $data, $actor) { $this->ensureMutable($encounter, $actor); return $this->laboratoryOrders->createOrder($encounter, $data, $actor); }
    public function addPrescription(ClinicalEncounter $encounter, array $data, $actor) { $this->ensureMutable($encounter, $actor); return $this->prescriptions->createPrescription($encounter, $data, $actor); }
    public function addProcedureOrder(ClinicalEncounter $encounter, array $data, $actor) { $this->ensureMutable($encounter, $actor); return $this->procedures->createOrder($encounter, $data, $actor); }
    public function createFollowUp(ClinicalEncounter $encounter, array $data, $actor) { $this->ensureMutable($encounter, $actor); return $this->appointments->createFollowUp($encounter, $data, $actor); }
    public function createReferral(ClinicalEncounter $encounter, array $data, $actor) { $this->ensureMutable($encounter, $actor); $referral = $this->referrals->createReferral($encounter, $data, $actor); $encounter->update(['status' => ClinicalEncounterStatus::Referred, 'outcome' => ClinicalOutcome::Referred]); return $referral; }

    public function signOff(ClinicalEncounter $encounter, $actor): ClinicalEncounter
    {
        $this->ensureMutable($encounter, $actor);
        $encounter->update(['signed_off_by' => $actor->id, 'signed_off_at' => now(), 'updated_by' => $actor->id]);
        return $encounter->refresh();
    }

    public function completeEncounter(ClinicalEncounter $encounter, $actor): ClinicalEncounter
    {
        return DB::transaction(function () use ($encounter, $actor) {
            $encounter = ClinicalEncounter::query()->lockForUpdate()->findOrFail($encounter->id);
            $this->ensureMutable($encounter, $actor);
            if (! $encounter->started_at || ! $encounter->provider_user_id) {
                throw ValidationException::withMessages(['encounter' => 'Consultation haijaanza kikamilifu.']);
            }
            if (blank($encounter->clinical_summary) && blank($encounter->assessment_notes) && blank($encounter->treatment_plan)) {
                throw ValidationException::withMessages(['clinical_summary' => 'Muhtasari au notes za clinical zinahitajika.']);
            }
            if ($encounter->follow_up_required && ! $encounter->follow_up_date) {
                throw ValidationException::withMessages(['follow_up_date' => 'Tarehe ya follow-up inahitajika.']);
            }
            if ($encounter->outcome === ClinicalOutcome::Referred && ! $encounter->referrals()->exists()) {
                throw ValidationException::withMessages(['referral' => 'Rufaa inahitajika kwa outcome ya referred.']);
            }
            if (! in_array($encounter->outcome, [ClinicalOutcome::Referred, ClinicalOutcome::Transferred, ClinicalOutcome::LeftAgainstAdvice], true)) {
                $this->diagnoses->validateCompletionDiagnosis($encounter);
            }
            if (! $encounter->signed_off_at) {
                throw ValidationException::withMessages(['signed_off' => 'Sign-off inahitajika kabla ya kukamilisha consultation.']);
            }

            $next = $this->determineNextVisitStatus($encounter);
            $encounter->update(['status' => $encounter->outcome === ClinicalOutcome::Referred ? ClinicalEncounterStatus::Referred : ClinicalEncounterStatus::Completed, 'completed_at' => now(), 'updated_by' => $actor->id]);
            if ($queue = PatientQueue::query()->where('visit_id', $encounter->visit_id)->where('department_id', $encounter->department_id)->where('queue_status', 'serving')->latest()->first()) {
                $this->workflow->completeQueue($queue, $actor);
            }
            $next === VisitStatus::Completed
                ? $this->workflow->completeVisit($encounter->visit, $actor)
                : $this->workflow->updateVisitStatus($encounter->visit, $next, $actor);
            $this->audit($actor, 'clinical_encounter_completed', $encounter, ['next_visit_status' => $next->value]);

            return $encounter->refresh();
        });
    }

    public function amendEncounter(ClinicalEncounter $encounter, string $field, ?string $value, string $reason, $actor): ClinicalEncounter
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu ya amendment inahitajika.']);
        }
        if (! $actor->can('clinical-encounters.amend')) {
            throw ValidationException::withMessages(['permission' => 'Huna ruhusa ya amendment.']);
        }
        $old = $encounter->{$field};
        $encounter->update([$field => $value, 'amendment_reason' => $reason, 'updated_by' => $actor->id]);
        ClinicalNoteAmendment::query()->create(['clinical_encounter_id' => $encounter->id, 'field_name' => $field, 'old_value' => $old, 'new_value' => $value, 'reason' => $reason, 'amended_by' => $actor->id, 'amended_at' => now(), 'created_at' => now()]);
        $this->audit($actor, 'clinical_encounter_amended', $encounter, ['field' => $field]);
        return $encounter->refresh();
    }

    public function determineNextVisitStatus(ClinicalEncounter $encounter): VisitStatus
    {
        if ($encounter->outcome === ClinicalOutcome::Referred) {
            return VisitStatus::Referred;
        }
        if ($encounter->outcome === ClinicalOutcome::AdmittedBedRest) {
            return VisitStatus::AwaitingBed;
        }
        if ($encounter->laboratoryOrders()->whereIn('status', ['awaiting_payment', 'ordered', 'sample_pending', 'processing'])->exists()) {
            return VisitStatus::AwaitingLab;
        }
        if ($encounter->procedureOrders()->where('status', 'awaiting_payment')->exists()) {
            return VisitStatus::AwaitingPayment;
        }
        if ($encounter->prescriptions()->whereIn('status', ['prescribed', 'awaiting_payment'])->exists()) {
            return VisitStatus::AwaitingPharmacy;
        }
        return VisitStatus::Completed;
    }

    private function ensureMutable(ClinicalEncounter $encounter, $actor): void
    {
        if (in_array($encounter->status, [ClinicalEncounterStatus::Completed, ClinicalEncounterStatus::Cancelled, ClinicalEncounterStatus::Referred], true) && ! $actor->can('clinical-encounters.amend')) {
            throw ValidationException::withMessages(['encounter' => 'Clinical record iliyokamilika haiwezi kubadilishwa bila amendment.']);
        }
    }

    private function audit($actor, string $event, ClinicalEncounter $encounter, array $extra = []): void
    {
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => $event, 'subject_type' => $encounter::class, 'subject_id' => $encounter->id, 'new_values' => $extra]);
    }
}
