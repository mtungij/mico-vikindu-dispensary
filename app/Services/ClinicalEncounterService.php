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
use App\Models\ObservationAdmission;
use App\Models\PatientQueue;
use App\Models\PhysicalExamination;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Support\Carbon;
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
        private readonly PrescriptionBillingService $prescriptionBilling,
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
        return DB::transaction(function () use ($encounter, $data, $actor): ClinicalEncounter {
            $encounter = ClinicalEncounter::query()->lockForUpdate()->findOrFail($encounter->id);
            $allowed = $this->draftFields($data);
            $this->ensureMutable($encounter);
            Gate::forUser($actor)->authorize('update', $encounter);
            $encounter->update([...$allowed, 'updated_by' => $actor->id]);
            $this->audit($actor, 'clinical_encounter_draft_saved', $encounter, ['fields' => array_keys($allowed)]);

            return $encounter->refresh();
        });
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
        if (in_array($encounter->status, [ClinicalEncounterStatus::Completed, ClinicalEncounterStatus::Cancelled, ClinicalEncounterStatus::Referred], true)) {
            throw ValidationException::withMessages([
                'encounter' => $encounter->status === ClinicalEncounterStatus::Completed
                    ? 'Laboratory orders cannot be added because this consultation is already completed.'
                    : 'Laboratory orders cannot be added because this consultation is no longer active.',
            ]);
        }

        Gate::forUser($actor)->authorize('create', [LaboratoryOrder::class, $encounter]);
        $this->ensureMutable($encounter, $actor);

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
        $encounter->update(['outcome' => ClinicalOutcome::Referred]);

        return $referral;
    }

    public function signOff(ClinicalEncounter $encounter, $actor, array $data = []): ClinicalEncounter
    {
        Gate::forUser($actor)->authorize('signOff', $encounter);

        return DB::transaction(function () use ($encounter, $actor, $data): ClinicalEncounter {
            $encounter = ClinicalEncounter::query()->lockForUpdate()->findOrFail($encounter->id);
            $this->ensureOpenForFinalization($encounter);
            if ($encounter->signed_off_at || $encounter->signed_off_by) {
                throw ValidationException::withMessages(['encounter' => 'This consultation has already been finalized.']);
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
                'signed_content_hash' => $this->clinicalContentHash($encounter),
                'updated_by' => $actor->id,
            ]);
            $this->audit($actor, 'clinical_encounter_signed_off', $encounter, [
                'signed_content_hash' => $encounter->signed_content_hash,
            ]);

            return $encounter->refresh();
        });
    }

    public function completeEncounter(ClinicalEncounter $encounter, $actor, array $data = []): ClinicalEncounter
    {
        return DB::transaction(function () use ($encounter, $actor, $data) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($encounter->visit_id);
            $encounter = ClinicalEncounter::query()->lockForUpdate()->findOrFail($encounter->id);
            $encounter->setRelation('visit', $visit);

            Gate::forUser($actor)->authorize('complete', $encounter);

            if ($encounter->completed_at || $encounter->status === ClinicalEncounterStatus::Completed) {
                return $encounter->refresh();
            }

            $this->ensureOpenForFinalization($encounter);
            $draft = $this->draftFields($data);
            if (($draft['outcome'] ?? null) === ClinicalOutcome::FollowUp->value) {
                $draft['follow_up_required'] = true;
                $draft['follow_up_date'] ??= $this->followUpDateFromData($data);
            }
            $encounter->update([
                ...$draft,
                'updated_by' => $actor->id,
            ]);
            $encounter->refresh();
            if ($data !== []) {
                $this->audit($actor, 'clinical_encounter_draft_saved', $encounter, [
                    'fields' => array_keys($this->draftFields($data)),
                    'source' => 'complete_consultation',
                ]);
            }

            $this->validateClinicalContentForFinalization($encounter, $data);
            $this->validateClinicalContentFacility($encounter);
            $this->validateOutcomeConsistency($encounter);
            $this->ensureFollowUpAppointment($encounter, $data, $actor);
            $completedAt = now();
            $wasSigned = (bool) ($encounter->signed_off_by && $encounter->signed_off_at);

            $encounter->prescriptions()
                ->where('status', PrescriptionStatus::Draft->value)
                ->get()
                ->each(fn ($prescription) => $this->prescriptions->finalizePrescription($prescription, $actor));

            $encounter->load('visit.invoice');
            $destinations = $this->resolveRequiredDestinations($encounter);
            $next = $this->determineNextVisitStatus($encounter);
            $encounter->update([
                'status' => $encounter->outcome === ClinicalOutcome::Referred ? ClinicalEncounterStatus::Referred : ClinicalEncounterStatus::Completed,
                'signed_off_by' => $encounter->signed_off_by ?: $actor->id,
                'signed_off_at' => $encounter->signed_off_at ?: $completedAt,
                'signed_content_hash' => $this->clinicalContentHash($encounter),
                'completed_by' => $actor->id,
                'completed_at' => $completedAt,
                'updated_by' => $actor->id,
            ]);
            if (! $wasSigned) {
                $this->audit($actor, 'clinical_encounter_signed_off', $encounter, [
                    'signed_content_hash' => $encounter->signed_content_hash,
                    'source' => 'complete_consultation',
                ]);
            }

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
                'final_outcome' => $encounter->outcome?->value,
                'signed_off_by' => $encounter->signed_off_by,
                'completed_by' => $encounter->completed_by,
                'next_visit_status' => $next->value,
                'destinations' => array_keys($destinations),
            ]);

            return $encounter->refresh();
        });
    }

    /** @return array<int, string> */
    public function completionDestinations(ClinicalEncounter $encounter): array
    {
        $destinations = [];
        if ($this->hasActiveProcedureWork($encounter)) {
            $destinations[] = 'Procedures';
        }
        if ($this->hasReadyPharmacyWork($encounter)) {
            $destinations[] = 'Pharmacy';
        }

        return array_values(array_unique($destinations));
    }

    public function amendEncounter(ClinicalEncounter $encounter, string $field, ?string $value, string $reason, $actor): ClinicalEncounter
    {
        if ($encounter->completed_at || in_array($encounter->status, [ClinicalEncounterStatus::Completed, ClinicalEncounterStatus::Cancelled, ClinicalEncounterStatus::Referred], true)) {
            throw ValidationException::withMessages([
                'encounter' => 'This consultation is completed and cannot be edited.',
            ]);
        }
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
        if (in_array($encounter->outcome, [ClinicalOutcome::AdmittedBedRest, ClinicalOutcome::Observation], true)) {
            return VisitStatus::AwaitingBed;
        }
        if ($this->hasActiveProcedureWork($encounter)) {
            return VisitStatus::AwaitingPayment;
        }
        if ($this->hasActivePharmacyWork($encounter)) {
            return $this->hasReadyPharmacyWork($encounter)
                ? VisitStatus::AwaitingPharmacy
                : VisitStatus::AwaitingPayment;
        }

        return VisitStatus::Completed;
    }

    private function ensureMutable(ClinicalEncounter $encounter, $actor = null): void
    {
        $encounter->loadMissing('visit');
        if ($encounter->isReadOnly()) {
            throw ValidationException::withMessages([
                'encounter' => 'Consultation hii tayari imekamilika na haiwezi kuhaririwa.',
            ]);
        }
    }

    private function ensureOpenForFinalization(ClinicalEncounter $encounter): void
    {
        $encounter->loadMissing('visit');
        if ($encounter->isReadOnly()) {
            throw ValidationException::withMessages(['encounter' => 'Consultation hii tayari imekamilika na haiwezi kuhaririwa.']);
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
        if ($this->hasActiveProcedureWork($encounter)) {
            $serviceDepartmentIds = $encounter->procedureOrders()
                ->whereIn('status', ['ordered', 'awaiting_payment', 'scheduled', 'in_progress'])
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
                'Procedures are not configured correctly.',
                true,
            );
        }
        if ($this->hasActivePharmacyWork($encounter)) {
            $destinations['Pharmacy'] = $this->requiredDepartment(
                $encounter,
                ['PHA'],
                'Pharmacy is not configured correctly.',
                true,
            );
        }
        if (in_array($encounter->outcome, [ClinicalOutcome::AdmittedBedRest, ClinicalOutcome::Observation], true)) {
            $destinations['Admission'] = $this->requiredDepartment(
                $encounter,
                ['BED'],
                'Admission is not configured correctly.',
                true,
            );
        }

        return $destinations;
    }

    /** @param array<string, Department> $destinations */
    private function createDownstreamQueues(ClinicalEncounter $encounter, array $destinations, $actor): void
    {
        foreach ($destinations as $name => $department) {
            if ($name === 'Pharmacy' && (! $this->hasReadyPharmacyWork($encounter) || $encounter->outcome === ClinicalOutcome::Referred)) {
                continue;
            }
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function clinicalContentHash(ClinicalEncounter $encounter, array $overrides = []): string
    {
        $content = [];
        foreach ($this->clinicalFieldNames() as $field) {
            $content[$field] = $this->normalizeClinicalValue(
                array_key_exists($field, $overrides) ? $overrides[$field] : $encounter->{$field},
            );
        }

        return hash('sha256', json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function normalizeClinicalValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_bool($value)) {
            return $value;
        }

        return $value === null ? '' : (string) $value;
    }

    /** @return array<int, string> */
    private function clinicalFieldNames(): array
    {
        return [
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
        ];
    }

    private function validateClinicalContentForFinalization(ClinicalEncounter $encounter, array $data = []): void
    {
        $errors = [];
        if (! $encounter->started_at || ! $encounter->provider_user_id) {
            $errors['encounter'] = 'The consultation has not been started correctly.';
        }
        $hasClinicalContent = filled($encounter->clinical_summary)
            || filled($encounter->assessment_notes)
            || filled($encounter->treatment_plan)
            || $encounter->diagnoses()->where('status', '!=', 'entered_in_error')->exists()
            || $encounter->prescriptions()->where('status', '!=', 'cancelled')->exists()
            || $encounter->laboratoryOrders()->where('status', '!=', 'cancelled')->exists()
            || $encounter->procedureOrders()->where('status', '!=', 'cancelled')->exists();
        if (! $hasClinicalContent) {
            $errors['clinical_content'] = 'Add a diagnosis, treatment plan, prescription, laboratory order, or clinical summary before completing.';
        }
        if (! $encounter->outcome || $encounter->outcome === ClinicalOutcome::Ongoing) {
            $errors['form.outcome'] = 'Select a final consultation outcome.';
        }
        if ($encounter->follow_up_required && ! $encounter->follow_up_date) {
            $errors['form.follow_up_date'] = 'A follow-up date is required.';
        }
        if ($encounter->outcome === ClinicalOutcome::Referred && ! $encounter->referrals()->exists()) {
            $errors['referral'] = 'Add referral details before completing.';
        }
        if ($encounter->outcome === ClinicalOutcome::FollowUp && ! $encounter->appointments()->exists()) {
            if (! $encounter->follow_up_date && ! $this->followUpDateFromData($data)) {
                $errors['form.follow_up_date'] = 'Add a follow-up date.';
            }
            if (blank($data['follow_up_reason'] ?? null)) {
                $errors['follow_up_reason'] = 'Add a follow-up reason.';
            }
            if (blank($data['follow_up_department_id'] ?? null)) {
                $errors['follow_up_department_id'] = 'Select a follow-up clinic or department.';
            }
        }

        if ($encounter->laboratoryOrders()
            ->where('status', '!=', 'cancelled')
            ->where(fn ($orders) => $orders
                ->where('payment_status', 'pending')
                ->orWhereHas('items', fn ($query) => $query
                    ->whereNotIn('status', ['cancelled'])
                    ->where(fn ($query) => $query
                        ->where('result_status', '!=', LaboratoryResultStatus::Released->value)
                        ->orWhereNull('result_status'))))
            ->exists()) {
            $errors['laboratory'] = 'Consultation cannot be completed because some laboratory orders are not yet verified and released.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateClinicalContentFacility(ClinicalEncounter $encounter): void
    {
        $facilityId = $encounter->facility_id;
        $hasForeignContent = $encounter->visit->facility_id !== $facilityId
            || $encounter->laboratoryOrders()->where('facility_id', '!=', $facilityId)->exists()
            || $encounter->laboratoryOrders()
                ->whereHas('items.service', fn ($query) => $query->where('facility_id', '!=', $facilityId))
                ->exists()
            || $encounter->prescriptions()->where('facility_id', '!=', $facilityId)->exists()
            || $encounter->prescriptions()
                ->whereHas('items.medicine', fn ($query) => $query->where('facility_id', '!=', $facilityId))
                ->exists()
            || $encounter->prescriptions()
                ->whereHas('items.service', fn ($query) => $query->where('facility_id', '!=', $facilityId))
                ->exists()
            || $encounter->procedureOrders()->where('facility_id', '!=', $facilityId)->exists()
            || $encounter->procedureOrders()
                ->whereHas('service', fn ($query) => $query->where('facility_id', '!=', $facilityId))
                ->exists();

        if ($hasForeignContent) {
            throw ValidationException::withMessages([
                'facility' => 'All selected clinical services must belong to the current facility.',
            ]);
        }
    }

    private function validateOutcomeConsistency(ClinicalEncounter $encounter): void
    {
        $hasActiveAdmission = ObservationAdmission::query()
            ->where('visit_id', $encounter->visit_id)
            ->whereIn('status', ['awaiting_payment', 'awaiting_bed', 'admitted', 'under_observation', 'ready_for_discharge'])
            ->exists();

        if ($hasActiveAdmission && ! in_array($encounter->outcome, [ClinicalOutcome::AdmittedBedRest, ClinicalOutcome::Observation], true)) {
            throw ValidationException::withMessages([
                'outcome' => 'The selected final outcome conflicts with the current admission order.',
            ]);
        }
    }

    private function ensureFollowUpAppointment(ClinicalEncounter $encounter, array $data, $actor): void
    {
        if ($encounter->outcome !== ClinicalOutcome::FollowUp || $encounter->appointments()->exists()) {
            return;
        }

        $date = $encounter->follow_up_date?->toDateString() ?? $this->followUpDateFromData($data);
        $this->appointments->createFollowUp($encounter, [
            'scheduled_start' => Carbon::parse($date)->setTime(8, 0)->toDateTimeString(),
            'department_id' => (int) $data['follow_up_department_id'],
            'reason' => $data['follow_up_reason'],
        ], $actor);
    }

    private function followUpDateFromData(array $data): ?string
    {
        if (filled($data['follow_up_date'] ?? null)) {
            return Carbon::parse($data['follow_up_date'])->toDateString();
        }
        if (filled($data['follow_up_scheduled_start'] ?? null)) {
            return Carbon::parse($data['follow_up_scheduled_start'])->toDateString();
        }

        return null;
    }

    private function hasActivePharmacyWork(ClinicalEncounter $encounter): bool
    {
        return $encounter->prescriptions()
            ->whereIn('status', ['draft', 'prescribed', 'awaiting_payment', 'partially_dispensed'])
            ->exists();
    }

    private function hasReadyPharmacyWork(ClinicalEncounter $encounter): bool
    {
        return $encounter->prescriptions()
            ->whereIn('status', [
                PrescriptionStatus::Prescribed->value,
                PrescriptionStatus::PartiallyDispensed->value,
            ])
            ->get()
            ->contains(fn (Prescription $prescription) => $this->prescriptionBilling->isCleared($prescription));
    }

    private function hasPatientFacingLaboratoryWork(ClinicalEncounter $encounter): bool
    {
        return $encounter->laboratoryOrders()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereHas('items', fn ($query) => $query
                ->whereNull('sample_id')
                ->where(fn ($query) => $query
                    ->whereNull('result_status')
                    ->orWhereNotIn('result_status', ['verified', 'released', 'cancelled', 'entered_in_error']))
                ->whereNotIn('status', ['completed', 'cancelled']))
            ->exists();
    }

    private function hasActiveProcedureWork(ClinicalEncounter $encounter): bool
    {
        return $encounter->procedureOrders()
            ->whereIn('status', ['ordered', 'awaiting_payment', 'scheduled', 'in_progress'])
            ->exists();
    }

    private function audit($actor, string $event, ClinicalEncounter $encounter, array $extra = []): void
    {
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => $event, 'subject_type' => $encounter::class, 'subject_id' => $encounter->id, 'new_values' => $extra]);
    }
}
