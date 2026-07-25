<?php

namespace App\Services;

use App\Enums\ClinicalEncounterStatus;
use App\Enums\ClinicalEncounterType;
use App\Enums\ClinicalOutcome;
use App\Enums\LaboratoryResultStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\ClinicalComplaint;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalNoteAmendment;
use App\Models\Department;
use App\Models\LaboratoryOrder;
use App\Models\PatientQueue;
use App\Models\PhysicalExamination;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        private readonly VisitClosureService $visitClosure,
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

            $isLaboratoryReview = $visit->visit_status === VisitStatus::AwaitingDoctorReview;
            $parentEncounter = $isLaboratoryReview
                ? ClinicalEncounter::query()
                    ->where('visit_id', $visit->id)
                    ->where('status', ClinicalEncounterStatus::Completed)
                    ->whereHas('laboratoryOrders.results', fn ($query) => $query
                        ->where('result_status', 'released')
                        ->whereNull('reviewed_at'))
                    ->latest('completed_at')
                    ->first()
                : null;

            $encounter = ClinicalEncounter::query()->create([
                'facility_id' => $visit->facility_id,
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'department_id' => $visit->current_department_id,
                'parent_encounter_id' => $parentEncounter?->id,
                'encounter_type' => $isLaboratoryReview ? ClinicalEncounterType::FollowUp : ClinicalEncounterType::Opd,
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
        $allowed = $this->draftFields($data);
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

    public function addDiagnosis(ClinicalEncounter $encounter, array $data, $actor)
    {
        $this->ensureMutable($encounter, $actor);

        return $this->diagnoses->addDiagnosis($encounter, $data, $actor);
    }

    public function addLabOrder(ClinicalEncounter $encounter, array $data, $actor): LaboratoryOrder
    {
        if (in_array($encounter->status, [ClinicalEncounterStatus::SignedOff, ClinicalEncounterStatus::Completed, ClinicalEncounterStatus::Cancelled, ClinicalEncounterStatus::Referred], true)) {
            throw ValidationException::withMessages([
                'encounter' => $encounter->status === ClinicalEncounterStatus::Completed
                    ? 'Laboratory orders cannot be added because this consultation is already completed.'
                    : 'Laboratory orders cannot be added because this consultation is no longer active.',
            ]);
        }

        Gate::forUser($actor)->authorize('create', [LaboratoryOrder::class, $encounter]);

        return $this->laboratoryOrders->createOrder($encounter, $data, $actor);
    }

    public function addPrescription(ClinicalEncounter $encounter, array $data, $actor)
    {
        $this->ensureMutable($encounter, $actor);

        return $this->prescriptions->createPrescription($encounter, $data, $actor);
    }

    public function addProcedureOrder(ClinicalEncounter $encounter, array $data, $actor)
    {
        $this->ensureMutable($encounter, $actor);

        return $this->procedures->createOrder($encounter, $data, $actor);
    }

    public function createFollowUp(ClinicalEncounter $encounter, array $data, $actor)
    {
        $this->ensureMutable($encounter, $actor);

        return $this->appointments->createFollowUp($encounter, $data, $actor);
    }

    public function createReferral(ClinicalEncounter $encounter, array $data, $actor)
    {
        $this->ensureMutable($encounter, $actor);
        $referral = $this->referrals->createReferral($encounter, $data, $actor);
        $encounter->update(['status' => ClinicalEncounterStatus::Referred, 'outcome' => ClinicalOutcome::Referred]);

        return $referral;
    }

    public function signOff(ClinicalEncounter $encounter, $actor, array $data = []): ClinicalEncounter
    {
        Gate::forUser($actor)->authorize('signOff', $encounter);

        return DB::transaction(function () use ($encounter, $actor, $data): ClinicalEncounter {
            $encounter = ClinicalEncounter::query()->lockForUpdate()->findOrFail($encounter->id);
            $this->ensureOpenForFinalization($encounter);
            if ($encounter->signed_off_at || $encounter->signed_off_by) {
                throw ValidationException::withMessages(['signed_off' => 'This consultation has already been signed off.']);
            }

            $encounter->update([
                ...$this->draftFields($data),
                'updated_by' => $actor->id,
            ]);
            $encounter = $encounter->refresh();
            $this->validateClinicalContentForFinalization($encounter);

            $encounter->update([
                'status' => ClinicalEncounterStatus::SignedOff,
                'signed_off_by' => $actor->id,
                'signed_off_at' => now(),
                'updated_by' => $actor->id,
            ]);
            $this->audit($actor, 'clinical_encounter_signed_off', $encounter);

            return $encounter->refresh();
        });
    }

    public function completeEncounter(ClinicalEncounter $encounter, $actor, array $data = []): ClinicalEncounter
    {
        Gate::forUser($actor)->authorize('complete', $encounter);

        return DB::transaction(function () use ($encounter, $actor, $data) {
            $encounter = ClinicalEncounter::query()->lockForUpdate()->findOrFail($encounter->id);
            $this->ensureOpenForFinalization($encounter);

            if ($data !== [] && ! $this->signedDataMatches($encounter, $data)) {
                throw ValidationException::withMessages([
                    'signed_off' => 'Clinical content changed after Sign Off. Revert the changes or use the amendment workflow.',
                ]);
            }

            $this->validateClinicalContentForFinalization($encounter);
            if (! $encounter->signed_off_at) {
                throw ValidationException::withMessages(['signed_off' => 'Sign Off is required before completing the consultation.']);
            }

            $destinations = $this->resolveRequiredDestinations($encounter);

            $encounter->prescriptions()
                ->where('status', PrescriptionStatus::Draft->value)
                ->get()
                ->each(fn ($prescription) => $this->prescriptions->finalizePrescription($prescription, $actor));

            $next = $this->determineNextVisitStatus($encounter);
            $encounter->update([
                'status' => $encounter->outcome === ClinicalOutcome::Referred ? ClinicalEncounterStatus::Referred : ClinicalEncounterStatus::Completed,
                'completed_by' => $actor->id,
                'completed_at' => now(),
                'updated_by' => $actor->id,
            ]);

            PatientQueue::query()
                ->where('visit_id', $encounter->visit_id)
                ->whereHas('department', fn ($query) => $query
                    ->where('facility_id', $encounter->facility_id)
                    ->where('code', 'OPD'))
                ->whereIn('queue_status', ['waiting', 'called', 'serving'])
                ->lockForUpdate()
                ->get()
                ->each(function (PatientQueue $queue) use ($actor): void {
                    $this->workflow->completeQueue($queue, $actor);
                });

            $this->createDownstreamQueues($encounter, $destinations, $actor);
            if ($next === VisitStatus::Referred) {
                $this->workflow->updateVisitStatus($encounter->visit, VisitStatus::Referred, $actor);
            } else {
                $this->visitClosure->evaluate($encounter->visit, $actor);
            }

            if ($encounter->encounter_type === ClinicalEncounterType::FollowUp && $encounter->parent_encounter_id) {
                $encounter->parentEncounter?->laboratoryOrders()
                    ->with('results')
                    ->get()
                    ->flatMap->results
                    ->where('result_status', LaboratoryResultStatus::Released)
                    ->whereNull('reviewed_at')
                    ->each(fn ($result) => $result->update([
                        'reviewed_by_clinician' => $actor->id,
                        'reviewed_at' => now(),
                        'updated_by' => $actor->id,
                    ]));
                $this->visitClosure->evaluate($encounter->visit->refresh(), $actor);
            }

            $this->audit($actor, 'clinical_encounter_completed', $encounter, [
                'next_visit_status' => $next->value,
                'destinations' => $this->completionDestinations($encounter),
            ]);

            return $encounter->refresh();
        });
    }

    /** @return array<int, string> */
    public function completionDestinations(ClinicalEncounter $encounter): array
    {
        $destinations = [];
        if ($encounter->laboratoryOrders()->exists()) {
            $destinations[] = 'Laboratory';
        }
        if ($encounter->procedureOrders()->exists()) {
            $destinations[] = 'Procedures';
        }
        if ($encounter->prescriptions()->exists()) {
            $destinations[] = 'Pharmacy';
        }
        if ($encounter->outcome === ClinicalOutcome::AdmittedBedRest) {
            $destinations[] = 'Admission';
        }
        if ($encounter->referrals()->exists()) {
            $destinations[] = 'Referral';
        }
        if ($encounter->appointments()->exists() || $encounter->follow_up_required) {
            $destinations[] = 'Follow-up';
        }

        return array_values(array_unique($destinations));
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
        if ($encounter->laboratoryOrders()->exists()) {
            return VisitStatus::AwaitingLab;
        }
        if ($encounter->procedureOrders()->exists()) {
            return VisitStatus::AwaitingPayment;
        }
        if ($encounter->prescriptions()->exists()) {
            return VisitStatus::AwaitingPharmacy;
        }

        return VisitStatus::Completed;
    }

    private function ensureMutable(ClinicalEncounter $encounter, $actor): void
    {
        if ($encounter->status === ClinicalEncounterStatus::SignedOff || $encounter->signed_off_at) {
            throw ValidationException::withMessages([
                'signed_off' => 'This consultation is signed off and is read-only. Use the amendment workflow for changes.',
            ]);
        }
        if (in_array($encounter->status, [ClinicalEncounterStatus::Completed, ClinicalEncounterStatus::Cancelled, ClinicalEncounterStatus::Referred], true) && ! $actor->can('clinical-encounters.amend')) {
            throw ValidationException::withMessages(['encounter' => 'Clinical record iliyokamilika haiwezi kubadilishwa bila amendment.']);
        }
    }

    private function ensureOpenForFinalization(ClinicalEncounter $encounter): void
    {
        if ($encounter->completed_at || in_array($encounter->status, [ClinicalEncounterStatus::Completed, ClinicalEncounterStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['encounter' => 'This consultation is already closed.']);
        }
    }

    private function draftFields(array $data): array
    {
        return collect($data)->only([
            'chief_complaint',
            'history_of_presenting_illness',
            'past_medical_history',
            'surgical_history',
            'medication_history',
            'allergy_history',
            'family_history',
            'social_history',
            'obstetric_history',
            'gynecological_history',
            'review_of_systems',
            'physical_examination',
            'clinical_summary',
            'assessment_notes',
            'treatment_plan',
            'discharge_instructions',
            'follow_up_required',
            'follow_up_date',
            'outcome',
        ])->all();
    }

    /** @return array<string, Department> */
    private function resolveRequiredDestinations(ClinicalEncounter $encounter): array
    {
        $destinations = [];
        if ($encounter->laboratoryOrders()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            $destinations['Laboratory'] = $this->requiredDepartment(
                $encounter,
                ['LAB'],
                'Consultation haiwezi kukamilika kwa sababu Laboratory haijawekwa vizuri.',
                true,
            );
        }
        if ($encounter->procedureOrders()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            $serviceDepartmentIds = $encounter->procedureOrders()
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->with('service:id,department_id')
                ->get()
                ->pluck('service.department_id')
                ->filter()
                ->unique();
            $department = $serviceDepartmentIds->count() === 1
                ? Department::query()
                    ->where('facility_id', $encounter->facility_id)
                    ->whereKey($serviceDepartmentIds->first())
                    ->where('is_active', true)
                    ->where('can_receive_patients', true)
                    ->where('queue_enabled', true)
                    ->first()
                : null;
            $destinations['Procedures'] = $department ?: $this->requiredDepartment(
                $encounter,
                ['PRC', 'PRO'],
                'Consultation haiwezi kukamilika kwa sababu Procedure destination haijawekwa vizuri.',
                true,
            );
        }
        if ($encounter->prescriptions()->whereNotIn('status', ['dispensed', 'cancelled'])->exists()) {
            $destinations['Pharmacy'] = $this->requiredDepartment(
                $encounter,
                ['PHA'],
                'Consultation haiwezi kukamilika kwa sababu Pharmacy haijawekwa vizuri.',
                true,
            );
        }
        if ($encounter->outcome === ClinicalOutcome::AdmittedBedRest) {
            $destinations['Admission'] = $this->requiredDepartment(
                $encounter,
                ['BED'],
                'Consultation haiwezi kukamilika kwa sababu Admission destination haijawekwa vizuri.',
                false,
            );
        }

        return $destinations;
    }

    /** @param array<string, Department> $destinations */
    private function createDownstreamQueues(ClinicalEncounter $encounter, array $destinations, $actor): void
    {
        foreach ($destinations as $name => $department) {
            $status = match ($name) {
                'Laboratory' => VisitStatus::AwaitingLab,
                'Pharmacy' => VisitStatus::AwaitingPharmacy,
                'Admission' => VisitStatus::AwaitingBed,
                default => VisitStatus::AwaitingPayment,
            };
            $this->workflow->createQueue(
                $encounter->visit->refresh(),
                $department,
                $actor,
                $status,
                "{$name} destination created after consultation",
                true,
                false,
            );
        }
    }

    private function requiredDepartment(
        ClinicalEncounter $encounter,
        array $codes,
        string $message,
        bool $queueRequired,
    ): Department {
        $department = Department::query()
            ->where('facility_id', $encounter->facility_id)
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->where('can_receive_patients', true)
            ->when($queueRequired, fn ($query) => $query->where('queue_enabled', true))
            ->first();
        if (! $department) {
            throw ValidationException::withMessages(['destination' => $message]);
        }

        return $department;
    }

    private function signedDataMatches(ClinicalEncounter $encounter, array $data): bool
    {
        foreach ($this->draftFields($data) as $field => $value) {
            $stored = $encounter->{$field};
            if ($stored instanceof \BackedEnum) {
                $stored = $stored->value;
            } elseif ($stored instanceof \DateTimeInterface) {
                $stored = $stored->format('Y-m-d');
            }
            if (is_bool($stored) || is_bool($value)) {
                if ((bool) $stored !== (bool) $value) {
                    return false;
                }
            } elseif ((string) ($stored ?? '') !== (string) ($value ?? '')) {
                return false;
            }
        }

        return true;
    }

    private function validateClinicalContentForFinalization(ClinicalEncounter $encounter): void
    {
        if (! $encounter->started_at || ! $encounter->provider_user_id) {
            throw ValidationException::withMessages(['encounter' => 'The consultation has not been started correctly.']);
        }
        if (blank($encounter->clinical_summary) && blank($encounter->assessment_notes) && blank($encounter->treatment_plan)) {
            throw ValidationException::withMessages(['form.clinical_summary' => 'A clinical summary, assessment note, or treatment plan is required.']);
        }
        if (! $encounter->outcome || $encounter->outcome === ClinicalOutcome::Ongoing) {
            throw ValidationException::withMessages(['form.outcome' => 'Select a final consultation outcome.']);
        }
        if ($encounter->follow_up_required && ! $encounter->follow_up_date) {
            throw ValidationException::withMessages(['form.follow_up_date' => 'A follow-up date is required.']);
        }
        if ($encounter->outcome === ClinicalOutcome::Referred && ! $encounter->referrals()->exists()) {
            throw ValidationException::withMessages(['referral' => 'Rufaa inahitajika kwa outcome ya referred.']);
        }
        if (! in_array($encounter->outcome, [ClinicalOutcome::Referred, ClinicalOutcome::Transferred, ClinicalOutcome::LeftAgainstAdvice], true)) {
            $this->diagnoses->validateCompletionDiagnosis($encounter);
        }
    }

    private function audit($actor, string $event, ClinicalEncounter $encounter, array $extra = []): void
    {
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => $event, 'subject_type' => $encounter::class, 'subject_id' => $encounter->id, 'new_values' => $extra]);
    }
}
