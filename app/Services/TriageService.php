<?php

namespace App\Services;

use App\Enums\TriageStatus;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\PatientQueue;
use App\Models\TriageAssessment;
use App\Models\Visit;
use App\Models\VisitMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TriageService
{
    public function __construct(
        private readonly VitalSignAssessmentService $vitals,
        private readonly ClinicalAlertService $alerts,
        private readonly QueueNumberService $queueNumbers,
        private readonly WorkflowService $workflow,
    ) {}

    public function startAssessment(Visit $visit, $actor): TriageAssessment
    {
        $this->assertVisitCanBeTriaged($visit);
        return DB::transaction(function () use ($visit, $actor) {
            $sequence = (int) TriageAssessment::query()->where('visit_id', $visit->id)->max('sequence_number') + 1;
            $assessment = TriageAssessment::query()->create([
                'facility_id' => $visit->facility_id,
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'queue_id' => $visit->queues()->latest()->first()?->id,
                'assessed_by' => $actor->id,
                'assessed_at' => now(),
                'sequence_number' => $sequence,
                'triage_level' => 'routine',
                'status' => TriageStatus::Draft,
                'created_by' => $actor->id,
            ]);
            $this->audit($actor, 'triage_started', $assessment);
            return $assessment;
        });
    }

    public function saveAssessment(TriageAssessment $assessment, array $data, $actor): TriageAssessment
    {
        if ($assessment->status === TriageStatus::Completed && ! $actor->can('triage.amend')) {
            throw ValidationException::withMessages(['assessment' => 'Triage iliyokamilika haiwezi kubadilishwa bila ruhusa ya amend.']);
        }

        $this->vitals->validateVitalRanges($data);
        $data['bmi'] = $this->vitals->calculateBmi($data['weight_kg'] ?? null, $data['height_cm'] ?? null);
        $data['updated_by'] = $actor->id;
        $assessment->update($data);

        return $assessment->refresh();
    }

    public function completeAssessment(TriageAssessment $assessment, array $data, $actor): TriageAssessment
    {
        return DB::transaction(function () use ($assessment, $data, $actor) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($assessment->visit_id);
            $this->assertVisitCanBeTriaged($visit);
            $assessment = $this->saveAssessment($assessment, $data, $actor);
            $assessment->update(['status' => TriageStatus::Completed, 'assessed_at' => now(), 'updated_by' => $actor->id]);

            if ($assessment->queue_id && $queue = PatientQueue::query()->find($assessment->queue_id)) {
                $this->workflow->completeQueue($queue, $actor);
            }

            $department = $this->determineNextDepartment($visit);
            if ($department) {
                $this->workflow->transferPatient($visit->refresh(), $department, 'Triage completed', $actor, VisitStatus::InQueue, true, $actor);
            }
            $this->alerts->createFromVitals($assessment->refresh(), $this->vitals->buildClinicalAlerts($assessment->toArray()));
            $this->audit($actor, 'triage_completed', $assessment);

            return $assessment->refresh();
        });
    }

    public function amendAssessment(TriageAssessment $assessment, array $data, string $reason, $actor): TriageAssessment
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu ya amendment inahitajika.']);
        }
        $data['amendment_reason'] = $reason;
        $updated = $this->saveAssessment($assessment, $data, $actor);
        $this->audit($actor, 'triage_amended', $updated, ['reason' => $reason]);
        return $updated;
    }

    public function determineNextDepartment(Visit $visit)
    {
        return $visit->destinationDepartment ?: $visit->currentDepartment;
    }

    public function createTargetQueue(Visit $visit, $actor): ?PatientQueue
    {
        $department = $visit->currentDepartment;
        if (! $department?->queue_enabled) {
            return null;
        }
        if (PatientQueue::query()->where('visit_id', $visit->id)->where('department_id', $department->id)->where('queue_status', 'waiting')->exists()) {
            return null;
        }

        return PatientQueue::query()->create([
            'facility_id' => $visit->facility_id,
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'department_id' => $department->id,
            'queue_number' => $this->queueNumbers->next($visit->facility_id, $department),
            'queue_date' => today(),
            'queue_status' => 'waiting',
            'priority' => $visit->priority,
            'created_by' => $actor->id,
        ]);
    }

    private function assertVisitCanBeTriaged(Visit $visit): void
    {
        if (in_array($visit->visit_status->value, ['completed', 'cancelled', 'discharged', 'referred'], true)) {
            throw ValidationException::withMessages(['visit' => 'Visit si active.']);
        }
        if ($visit->visit_status === VisitStatus::AwaitingPayment) {
            throw ValidationException::withMessages(['payment' => 'Mgonjwa anasubiri malipo kabla ya huduma.']);
        }
    }

    private function audit($actor, string $event, TriageAssessment $assessment, array $extra = []): void
    {
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => $event, 'subject_type' => $assessment::class, 'subject_id' => $assessment->id, 'new_values' => $extra]);
    }
}
