<?php

namespace App\Services;

use App\Enums\PrescriptionStatus;
use App\Enums\ProcedureOrderStatus;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;
use App\Models\ObservationAdmission;
use App\Models\PatientQueue;
use App\Models\Prescription;
use App\Models\Visit;
use App\Models\WorkflowSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitClosureService
{
    public function __construct(private readonly WorkflowService $workflow) {}

    public function evaluate(Visit $visit, $actor): Visit
    {
        return DB::transaction(function () use ($visit, $actor): Visit {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);

            if (in_array($visit->visit_status, [
                VisitStatus::Cancelled,
                VisitStatus::Referred,
                VisitStatus::Discharged,
            ], true)) {
                return $visit;
            }

            if ($visit->clinicalEncounters()
                ->whereIn('status', ['waiting', 'in_progress', 'signed_off', 'paused'])
                ->exists()) {
                return $visit;
            }

            $blockers = $this->blockers($visit);
            if ($blockers === []) {
                if ($visit->visit_status === VisitStatus::Completed) {
                    return $visit;
                }

                return $this->workflow->completeVisit($visit, $actor, 'All required patient-facing services completed');
            }

            [$status, $queue] = $this->legacyStatusAndQueue($visit, $blockers);
            if ($status === VisitStatus::AwaitingDoctorReview) {
                $queue = $this->ensureDoctorReviewQueue($visit, $actor);
            }
            $updates = [
                'visit_status' => $status,
                'current_queue_id' => $queue?->id,
                'current_assigned_user_id' => $queue?->assigned_to_user_id,
                'completed_at' => null,
                'updated_by' => $actor->id,
            ];
            if ($queue) {
                $updates['current_department_id'] = $queue->department_id;
            }
            $visit->update($updates);

            return $visit->refresh();
        });
    }

    /** Read-only projection using the same blockers and priority as closure. */
    public function workflowState(Visit $visit): array
    {
        $blockers = $this->blockers($visit);
        [$status, $queue, $reason] = $this->legacyStatusAndQueue($visit, $blockers);
        [, , $afterPayment] = $this->legacyStatusAndQueue($visit, array_values(array_diff($blockers, ['payment'])));

        return compact('blockers', 'status', 'queue', 'reason', 'afterPayment');
    }

    public function completeDepartmentQueues(Visit $visit, string $departmentCode, $actor): void
    {
        DB::transaction(function () use ($visit, $departmentCode, $actor): void {
            Visit::query()->lockForUpdate()->findOrFail($visit->id);
            PatientQueue::query()
                ->where('visit_id', $visit->id)
                ->where('facility_id', $visit->facility_id)
                ->whereHas('department', fn ($query) => $query
                    ->where('facility_id', $visit->facility_id)
                    ->where('code', strtoupper($departmentCode)))
                ->whereIn('queue_status', [
                    QueueStatus::Waiting->value,
                    QueueStatus::Called->value,
                    QueueStatus::Serving->value,
                ])
                ->lockForUpdate()
                ->get()
                ->each(function (PatientQueue $queue) use ($actor): void {
                    if (in_array($queue->queue_status, [QueueStatus::Waiting, QueueStatus::Called], true)) {
                        $this->workflow->startService($queue, $actor);
                    }
                    $this->workflow->completeQueue($queue->refresh(), $actor);
                });
        });
    }

    public function startDepartmentQueues(Visit $visit, string $departmentCode, $actor): void
    {
        DB::transaction(function () use ($visit, $departmentCode, $actor): void {
            Visit::query()->lockForUpdate()->findOrFail($visit->id);
            PatientQueue::query()
                ->where('visit_id', $visit->id)
                ->where('facility_id', $visit->facility_id)
                ->whereHas('department', fn ($query) => $query
                    ->where('facility_id', $visit->facility_id)
                    ->where('code', strtoupper($departmentCode)))
                ->whereIn('queue_status', [QueueStatus::Waiting->value, QueueStatus::Called->value])
                ->lockForUpdate()
                ->get()
                ->each(fn (PatientQueue $queue) => $this->workflow->startService($queue, $actor));
        });
    }

    public function cancelDepartmentQueues(Visit $visit, string $departmentCode, $actor, string $reason): void
    {
        DB::transaction(function () use ($visit, $departmentCode, $actor, $reason): void {
            Visit::query()->lockForUpdate()->findOrFail($visit->id);
            PatientQueue::query()
                ->where('visit_id', $visit->id)
                ->where('facility_id', $visit->facility_id)
                ->whereHas('department', fn ($query) => $query
                    ->where('facility_id', $visit->facility_id)
                    ->where('code', strtoupper($departmentCode)))
                ->whereIn('queue_status', [
                    QueueStatus::Waiting->value,
                    QueueStatus::Called->value,
                    QueueStatus::Serving->value,
                ])
                ->lockForUpdate()
                ->get()
                ->each(fn (PatientQueue $queue) => $this->workflow->cancelQueue($queue, $actor, $reason));
        });
    }

    /** @param array<int, int> $departmentIds */
    public function completeQueuesForDepartments(Visit $visit, array $departmentIds, $actor): void
    {
        if ($departmentIds === []) {
            return;
        }

        DB::transaction(function () use ($visit, $departmentIds, $actor): void {
            Visit::query()->lockForUpdate()->findOrFail($visit->id);
            PatientQueue::query()
                ->where('visit_id', $visit->id)
                ->where('facility_id', $visit->facility_id)
                ->whereIn('department_id', $departmentIds)
                ->whereIn('queue_status', ['waiting', 'called', 'serving'])
                ->lockForUpdate()
                ->get()
                ->each(function (PatientQueue $queue) use ($actor): void {
                    if (in_array($queue->queue_status, [QueueStatus::Waiting, QueueStatus::Called], true)) {
                        $this->workflow->startService($queue, $actor);
                    }
                    $this->workflow->completeQueue($queue->refresh(), $actor);
                });
        });
    }

    public function requiresDoctorReview(Visit $visit): bool
    {
        $hasDirectLaboratoryOrder = LaboratoryOrder::query()
            ->where('visit_id', $visit->id)
            ->where('facility_id', $visit->facility_id)
            ->where('source', LaboratoryOrder::SOURCE_RECEPTION_DIRECT)
            ->exists();
        $hasClinicianLaboratoryOrder = LaboratoryOrder::query()
            ->where('visit_id', $visit->id)
            ->where('facility_id', $visit->facility_id)
            ->where('source', '!=', LaboratoryOrder::SOURCE_RECEPTION_DIRECT)
            ->exists();
        if ($hasDirectLaboratoryOrder && ! $hasClinicianLaboratoryOrder) {
            return false;
        }

        $setting = WorkflowSetting::query()
            ->where('facility_id', $visit->facility_id)
            ->where('key', 'require_doctor_review_after_laboratory')
            ->first();
        $value = $setting?->value ?? false;
        if (is_array($value)) {
            $value = reset($value);
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /** @return array<int, string> */
    private function blockers(Visit $visit): array
    {
        $blockers = [];

        if (Invoice::query()
            ->where('visit_id', $visit->id)
            ->where('facility_id', $visit->facility_id)
            ->where('balance_amount', '>', 0)
            ->whereNotIn('invoice_status', ['voided', 'cancelled'])
            ->exists()) {
            $blockers[] = 'payment';
        }

        if (Prescription::query()
            ->where('visit_id', $visit->id)
            ->where('facility_id', $visit->facility_id)
            ->whereIn('status', [
                PrescriptionStatus::Draft->value,
                PrescriptionStatus::Prescribed->value,
                PrescriptionStatus::AwaitingPayment->value,
                PrescriptionStatus::PartiallyDispensed->value,
            ])->exists()) {
            $blockers[] = 'pharmacy';
        }

        $uncollectedLaboratoryWork = LaboratoryOrder::query()
            ->where('visit_id', $visit->id)
            ->where('facility_id', $visit->facility_id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereHas('items', fn ($query) => $query
                ->whereNull('sample_id')
                ->where(fn ($query) => $query
                    ->whereNull('result_status')
                    ->orWhereNotIn('result_status', ['verified', 'released', 'cancelled', 'entered_in_error']))
                ->whereNotIn('status', ['completed', 'cancelled']))
            ->exists();
        if ($uncollectedLaboratoryWork) {
            $blockers[] = 'laboratory';
        }

        if ($this->requiresDoctorReview($visit) && $this->hasPendingLaboratoryReview($visit)) {
            $blockers[] = 'doctor_review';
        }

        if ($visit->clinicalEncounters()
            ->whereHas('procedureOrders', fn ($query) => $query->whereIn('status', [
                ProcedureOrderStatus::Ordered->value,
                ProcedureOrderStatus::AwaitingPayment->value,
                ProcedureOrderStatus::Scheduled->value,
                ProcedureOrderStatus::InProgress->value,
            ]))->exists()) {
            $blockers[] = 'procedure';
        }

        $hasActiveAdmission = ObservationAdmission::query()
            ->where('visit_id', $visit->id)
            ->where('facility_id', $visit->facility_id)
            ->whereIn('status', ['awaiting_payment', 'awaiting_bed', 'admitted', 'under_observation', 'ready_for_discharge'])
            ->exists();
        $hasPendingAdmissionDecision = ClinicalEncounter::query()
            ->where('visit_id', $visit->id)
            ->where('facility_id', $visit->facility_id)
            ->whereIn('outcome', ['admitted_bed_rest', 'observation'])
            ->exists()
            && ObservationAdmission::query()->where('visit_id', $visit->id)
                ->where('facility_id', $visit->facility_id)->doesntExist();
        if ($hasActiveAdmission || $hasPendingAdmissionDecision) {
            $blockers[] = 'admission';
        }

        $activeQueueCodes = PatientQueue::query()
            ->where('visit_id', $visit->id)
            ->where('facility_id', $visit->facility_id)
            ->whereIn('queue_status', [
                QueueStatus::Waiting->value,
                QueueStatus::Called->value,
                QueueStatus::Serving->value,
            ])
            ->whereHas('department', fn ($query) => $query->whereIn('code', ['PHA', 'LAB', 'PRC', 'PRO', 'BED', 'OPD']))
            ->with('department:id,code')
            ->get()
            ->pluck('department.code');

        if ($activeQueueCodes->contains('PHA')) {
            $blockers[] = 'pharmacy';
        }
        if ($activeQueueCodes->contains('LAB')) {
            $blockers[] = 'laboratory';
        }
        if ($activeQueueCodes->intersect(['PRC', 'PRO'])->isNotEmpty()) {
            $blockers[] = 'procedure';
        }
        if ($activeQueueCodes->contains('BED')) {
            $blockers[] = 'admission';
        }

        return array_values(array_unique($blockers));
    }

    private function hasPendingLaboratoryReview(Visit $visit): bool
    {
        $hasUnreleasedWork = LaboratoryOrder::query()
            ->where('visit_id', $visit->id)
            ->where('facility_id', $visit->facility_id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->exists();

        return $hasUnreleasedWork || $this->hasReleasedUnreviewedResult($visit);
    }

    /** @param array<int, string> $blockers */
    private function legacyStatusAndQueue(Visit $visit, array $blockers): array
    {
        $priority = [
            'payment' => [VisitStatus::AwaitingPayment, ['BIL']],
            'admission' => [$this->admissionVisitStatus($visit), ['BED']],
            'laboratory' => [VisitStatus::AwaitingLab, ['LAB']],
            'doctor_review' => [
                $this->hasReleasedUnreviewedResult($visit) ? VisitStatus::AwaitingDoctorReview : VisitStatus::AwaitingResults,
                ['OPD'],
            ],
            'procedure' => [VisitStatus::AwaitingPayment, ['PRC', 'PRO']],
            'pharmacy' => [VisitStatus::AwaitingPharmacy, ['PHA']],
        ];

        foreach ($priority as $blocker => [$status, $codes]) {
            if (! in_array($blocker, $blockers, true)) {
                continue;
            }
            $queue = PatientQueue::query()
                ->where('visit_id', $visit->id)
                ->where('facility_id', $visit->facility_id)
                ->whereIn('queue_status', ['waiting', 'called', 'serving'])
                ->whereHas('department', fn ($query) => $query->whereIn('code', $codes))
                ->latest()
                ->first();

            return [$status, $queue, $blocker];
        }

        return [VisitStatus::InProgress, null, null];
    }

    public function pendingReviewResults(Visit $visit): Builder
    {
        return LaboratoryResult::query()
            ->where('facility_id', $visit->facility_id)
            ->whereHas('order', fn ($query) => $query
                ->where('facility_id', $visit->facility_id)
                ->where('patient_id', $visit->patient_id)
                ->where('visit_id', $visit->id)
                ->where('source', '!=', LaboratoryOrder::SOURCE_RECEPTION_DIRECT))
            ->where('result_status', 'released')
            ->whereNull('reviewed_at');
    }

    public function hasReleasedUnreviewedResult(Visit $visit): bool
    {
        return $this->requiresDoctorReview($visit) && $this->pendingReviewResults($visit)->exists();
    }

    public function doctorReviewParent(Visit $visit): ?ClinicalEncounter
    {
        return $visit->clinicalEncounters()
            ->where('facility_id', $visit->facility_id)
            ->where('patient_id', $visit->patient_id)
            ->where('status', 'completed')
            ->whereHas('department', fn ($query) => $query
                ->where('facility_id', $visit->facility_id)->where('code', 'OPD'))
            ->whereHas('laboratoryOrders.results', fn ($query) => $query
                ->whereIn('id', $this->pendingReviewResults($visit)->select('laboratory_results.id')))
            ->latest('completed_at')->latest('id')->first();
    }

    // Caller holds the visit lock, shared by release, opening and completion.
    public function ensureDoctorReviewQueue(Visit $visit, $actor): PatientQueue
    {
        $parent = $this->doctorReviewParent($visit);
        $department = Department::query()
            ->where('facility_id', $visit->facility_id)->where('code', 'OPD')
            ->where('is_active', true)->where('can_receive_patients', true)
            ->where('queue_enabled', true)
            ->when($parent, fn ($query) => $query->orderByRaw('id = ? desc', [$parent->department_id]))
            ->orderBy('id')->first();
        if (! $department) {
            throw ValidationException::withMessages(['visit' => 'An active OPD queue destination is required for laboratory review.']);
        }

        return $this->workflow->createQueue($visit, $department, $actor,
            VisitStatus::AwaitingDoctorReview, 'Laboratory results require doctor review', true);
    }

    private function admissionVisitStatus(Visit $visit): VisitStatus
    {
        if (ClinicalEncounter::query()
            ->where('visit_id', $visit->id)
            ->where('facility_id', $visit->facility_id)
            ->where('outcome', 'observation')
            ->exists()) {
            return VisitStatus::UnderObservation;
        }

        return ObservationAdmission::query()
            ->where('visit_id', $visit->id)
            ->where('facility_id', $visit->facility_id)
            ->whereIn('status', ['admitted', 'under_observation', 'ready_for_discharge'])
            ->exists()
                ? VisitStatus::UnderObservation
                : VisitStatus::AwaitingBed;
    }
}
