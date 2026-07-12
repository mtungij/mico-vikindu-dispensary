<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\PatientQueue;
use App\Models\QueueCall;
use App\Models\QueueTicket;
use App\Models\Visit;
use App\Models\VisitMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public function __construct(private readonly QueueNumberService $numbers) {}

    public function createQueue(Visit $visit, Department $department, $actor, ?VisitStatus $status = null, ?string $reason = null, bool $skipValidation = false): ?PatientQueue
    {
        return DB::transaction(function () use ($visit, $department, $actor, $status, $reason, $skipValidation): ?PatientQueue {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->assertSameFacility($visit, $department);
            if (! $skipValidation) {
                $this->validateWorkflow($visit, $department, $reason);
            }

            if (! $department->queue_enabled) {
                $this->movePatient($visit, $department, $status ?? VisitStatus::AwaitingDepartment, $actor, $reason ?? 'Department queue disabled');
                return null;
            }

            $existing = PatientQueue::query()
                ->where('visit_id', $visit->id)
                ->where('department_id', $department->id)
                ->whereIn('queue_status', [QueueStatus::Waiting->value, QueueStatus::Called->value, QueueStatus::Serving->value])
                ->latest()
                ->first();

            if ($existing) {
                $this->updateCurrentDepartment($visit, $department, $actor);
                $this->updateVisitStatus($visit, $status ?? VisitStatus::Waiting, $actor, $existing);
                return $existing->refresh();
            }

            $position = (int) PatientQueue::query()
                ->where('facility_id', $visit->facility_id)
                ->where('department_id', $department->id)
                ->whereDate('queue_date', today())
                ->whereIn('queue_status', [QueueStatus::Waiting->value, QueueStatus::Called->value])
                ->max('position') + 1;

            $queue = PatientQueue::query()->create([
                'facility_id' => $visit->facility_id,
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'department_id' => $department->id,
                'queue_number' => $this->numbers->next($visit->facility_id, $department),
                'queue_date' => today(),
                'queue_status' => QueueStatus::Waiting,
                'priority' => $visit->priority,
                'position' => $position,
                'checked_in_at' => now(),
                'created_by' => $actor->id,
            ]);

            $this->createMovement($visit, $visit->currentDepartment, $department, $reason ?? 'Queue created', $actor, 'queue_created');
            $this->updateVisitStatus($visit, $status ?? VisitStatus::Waiting, $actor, $queue);
            $this->updateCurrentDepartment($visit, $department, $actor, $queue);
            $this->audit($actor, 'workflow_queue_created', $queue, ['visit_id' => $visit->id]);

            return $queue->refresh();
        });
    }

    public function callNext(Department $department, $actor): ?PatientQueue
    {
        $queue = PatientQueue::query()
            ->where('facility_id', currentFacility()?->id)
            ->where('department_id', $department->id)
            ->whereDate('queue_date', today())
            ->where('queue_status', QueueStatus::Waiting)
            ->orderByRaw("case priority when 'emergency' then 1 when 'urgent' then 2 when 'high' then 3 when 'normal' then 4 else 5 end")
            ->orderBy('position')
            ->lockForUpdate()
            ->first();

        return $queue ? $this->callQueue($queue, $actor) : null;
    }

    public function callQueue(PatientQueue $queue, $actor): PatientQueue
    {
        return DB::transaction(function () use ($queue, $actor): PatientQueue {
            $queue = PatientQueue::query()->lockForUpdate()->findOrFail($queue->id);
            $callCount = QueueCall::query()->where('patient_queue_id', $queue->id)->max('call_count') ?: 0;
            $queue->update(['queue_status' => QueueStatus::Called, 'called_at' => now(), 'assigned_to_user_id' => $actor->id]);
            QueueCall::query()->create(['facility_id' => $queue->facility_id, 'patient_queue_id' => $queue->id, 'department_id' => $queue->department_id, 'queue_number' => $queue->queue_number, 'call_count' => $callCount + 1, 'called_at' => now(), 'called_by' => $actor->id]);
            $this->updateVisitStatus($queue->visit, VisitStatus::Called, $actor, $queue);
            $this->audit($actor, 'queue_called', $queue);
            return $queue->refresh();
        });
    }

    public function startService(PatientQueue $queue, $actor): PatientQueue
    {
        $waitingSeconds = $queue->checked_in_at ? $queue->checked_in_at->diffInSeconds(now()) : null;
        $queue->update(['queue_status' => QueueStatus::Serving, 'service_started_at' => now(), 'waiting_seconds' => $waitingSeconds, 'assigned_to_user_id' => $actor->id]);
        $this->updateVisitStatus($queue->visit, VisitStatus::Serving, $actor, $queue);
        $this->audit($actor, 'queue_service_started', $queue);
        return $queue->refresh();
    }

    public function completeQueue(PatientQueue $queue, $actor, ?VisitStatus $nextStatus = null): PatientQueue
    {
        $serviceSeconds = $queue->service_started_at ? $queue->service_started_at->diffInSeconds(now()) : null;
        $queue->update(['queue_status' => QueueStatus::Completed, 'service_completed_at' => now(), 'service_seconds' => $serviceSeconds]);
        if ($nextStatus) {
            $this->updateVisitStatus($queue->visit, $nextStatus, $actor, null);
        }
        $this->audit($actor, 'queue_completed', $queue);
        return $queue->refresh();
    }

    public function closeQueue(PatientQueue $queue, $actor): PatientQueue
    {
        return $this->completeQueue($queue, $actor);
    }

    public function skipQueue(PatientQueue $queue, $actor, ?string $reason = null): PatientQueue
    {
        $queue->update(['queue_status' => QueueStatus::Skipped, 'skipped_at' => now(), 'notes' => $reason]);
        $this->audit($actor, 'queue_skipped', $queue, ['reason' => $reason]);
        return $queue->refresh();
    }

    public function cancelQueue(PatientQueue $queue, $actor, ?string $reason = null): PatientQueue
    {
        $queue->update(['queue_status' => QueueStatus::Cancelled, 'cancelled_at' => now(), 'notes' => $reason]);
        $this->audit($actor, 'queue_cancelled', $queue, ['reason' => $reason]);
        return $queue->refresh();
    }

    public function requeue(PatientQueue $queue, $actor): PatientQueue
    {
        $queue->update(['queue_status' => QueueStatus::Waiting, 'requeued_at' => now(), 'called_at' => null, 'service_started_at' => null, 'assigned_to_user_id' => null]);
        $this->updateVisitStatus($queue->visit, VisitStatus::Waiting, $actor, $queue);
        $this->audit($actor, 'queue_requeued', $queue);
        return $queue->refresh();
    }

    public function transferPatient(Visit $visit, Department $toDepartment, string $reason, $actor, ?VisitStatus $status = null, bool $emergencyOverride = false, $authorizedBy = null): ?PatientQueue
    {
        return DB::transaction(function () use ($visit, $toDepartment, $reason, $actor, $status, $emergencyOverride, $authorizedBy): ?PatientQueue {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->validateWorkflow($visit, $toDepartment, $reason, $emergencyOverride);
            PatientQueue::query()->where('visit_id', $visit->id)->whereIn('queue_status', [QueueStatus::Waiting->value, QueueStatus::Called->value, QueueStatus::Serving->value])->update(['queue_status' => QueueStatus::Transferred->value, 'service_completed_at' => now()]);
            $this->createMovement($visit, $visit->currentDepartment, $toDepartment, $reason, $actor, 'department_transfer', $emergencyOverride, $authorizedBy);
            return $this->createQueue($visit->refresh(), $toDepartment, $actor, $status ?? VisitStatus::Waiting, $reason, $emergencyOverride);
        });
    }

    public function movePatient(Visit $visit, Department $toDepartment, VisitStatus $status, $actor, ?string $reason = null): Visit
    {
        $this->createMovement($visit, $visit->currentDepartment, $toDepartment, $reason ?? $status->value, $actor, 'patient_moved');
        $this->updateCurrentDepartment($visit, $toDepartment, $actor);
        $this->updateVisitStatus($visit, $status, $actor);
        return $visit->refresh();
    }

    public function completeVisit(Visit $visit, $actor, string $reason = 'Visit completed'): Visit
    {
        PatientQueue::query()->where('visit_id', $visit->id)->whereIn('queue_status', [QueueStatus::Waiting->value, QueueStatus::Called->value, QueueStatus::Serving->value])->update(['queue_status' => QueueStatus::Completed->value, 'service_completed_at' => now()]);
        $visit->update(['visit_status' => VisitStatus::Completed, 'completed_at' => now(), 'current_queue_id' => null, 'current_assigned_user_id' => null, 'updated_by' => $actor->id]);
        $this->createMovement($visit, $visit->currentDepartment, null, $reason, $actor, 'visit_completed');
        $this->audit($actor, 'visit_completed', $visit);
        return $visit->refresh();
    }

    public function cancelVisit(Visit $visit, $actor, string $reason): Visit
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu ya cancellation inahitajika.']);
        }
        PatientQueue::query()->where('visit_id', $visit->id)->whereIn('queue_status', [QueueStatus::Waiting->value, QueueStatus::Called->value, QueueStatus::Serving->value])->update(['queue_status' => QueueStatus::Cancelled->value, 'cancelled_at' => now(), 'notes' => $reason]);
        $visit->update(['visit_status' => VisitStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $reason, 'current_queue_id' => null, 'current_assigned_user_id' => null, 'updated_by' => $actor->id]);
        $this->createMovement($visit, $visit->currentDepartment, null, $reason, $actor, 'visit_cancelled');
        $this->audit($actor, 'visit_cancelled', $visit, ['reason' => $reason]);
        return $visit->refresh();
    }

    public function createMovement(Visit $visit, ?Department $from, ?Department $to, ?string $reason, $actor, string $type = 'patient_moved', bool $emergencyOverride = false, $authorizedBy = null): VisitMovement
    {
        $last = VisitMovement::query()->where('visit_id', $visit->id)->latest('moved_at')->first();

        $movement = VisitMovement::query()->create([
            'facility_id' => $visit->facility_id,
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'from_department_id' => $from?->id,
            'to_department_id' => $to?->id,
            'movement_type' => $type,
            'status' => 'completed',
            'reason' => $reason,
            'moved_by' => $actor->id,
            'moved_at' => now(),
            'movement_duration_seconds' => $last?->moved_at?->diffInSeconds(now()),
            'emergency_override' => $emergencyOverride,
            'authorized_by' => $authorizedBy?->id,
        ]);

        $this->audit($actor, 'patient_moved', $movement, ['from' => $from?->name, 'to' => $to?->name, 'reason' => $reason]);

        return $movement;
    }

    public function updateVisitStatus(Visit $visit, VisitStatus $status, $actor, ?PatientQueue $queue = null): Visit
    {
        $visit->update(['visit_status' => $status, 'current_queue_id' => $queue?->id, 'current_assigned_user_id' => $queue?->assigned_to_user_id, 'updated_by' => $actor->id]);
        return $visit->refresh();
    }

    public function updateCurrentDepartment(Visit $visit, Department $department, $actor, ?PatientQueue $queue = null): Visit
    {
        $visit->update(['current_department_id' => $department->id, 'destination_department_id' => $department->id, 'current_queue_id' => $queue?->id ?? $visit->current_queue_id, 'updated_by' => $actor->id]);
        return $visit->refresh();
    }

    public function createTicket(PatientQueue $queue, $actor): QueueTicket
    {
        $patient = $queue->patient;
        return QueueTicket::query()->create([
            'facility_id' => $queue->facility_id,
            'patient_queue_id' => $queue->id,
            'visit_id' => $queue->visit_id,
            'patient_id' => $queue->patient_id,
            'department_id' => $queue->department_id,
            'queue_number' => $queue->queue_number,
            'visit_number' => $queue->visit->visit_number,
            'patient_name' => trim($patient->first_name.' '.$patient->last_name),
            'qr_payload' => $queue->queue_number.'|'.$queue->visit->visit_number,
            'printed_at' => now(),
            'printed_by' => $actor->id,
        ]);
    }

    public function validateWorkflow(Visit $visit, Department $department, ?string $reason = null, bool $emergencyOverride = false): void
    {
        if ($visit->facility_id !== $department->facility_id) {
            abort(404);
        }
        if ($emergencyOverride) {
            if (blank($reason)) {
                throw ValidationException::withMessages(['override_reason' => 'Sababu ya emergency override inahitajika.']);
            }
            return;
        }
        $code = strtoupper((string) $department->code);
        if ($code === 'LAB' && ! $visit->clinicalEncounters()->whereHas('laboratoryOrders')->exists()) {
            throw ValidationException::withMessages(['laboratory' => 'Cannot enter Laboratory without Laboratory Order.']);
        }
        if ($code === 'PHA' && ! $visit->clinicalEncounters()->whereHas('prescriptions')->exists()) {
            throw ValidationException::withMessages(['pharmacy' => 'Cannot enter Pharmacy without Prescription.']);
        }
        if ($code === 'BED' && ! in_array($visit->visit_status?->value ?? $visit->visit_status, [VisitStatus::AwaitingBed->value, VisitStatus::UnderObservation->value], true)) {
            throw ValidationException::withMessages(['bed' => 'Cannot enter Bed Rest without Admission Order.']);
        }
        if ($code === 'DEN' && $visit->destination_department_id !== $department->id && ! str_contains(strtolower((string) $reason), 'dental')) {
            throw ValidationException::withMessages(['dental' => 'Cannot enter Dental without Dental Visit.']);
        }
    }

    public function routeAfterRegistration(Visit $visit, $actor, bool $paymentFirst, bool $useTriage): ?PatientQueue
    {
        $destination = $visit->destinationDepartment;
        $billing = Department::query()->where('facility_id', $visit->facility_id)->where('code', 'BIL')->first();
        if ($paymentFirst && $visit->payer_type === PayerType::Cash && $billing) {
            return $this->createQueue($visit, $billing, $actor, VisitStatus::AwaitingPayment, 'Payment required before service');
        }
        if ($useTriage && $destination?->requires_triage) {
            $triage = Department::query()->where('facility_id', $visit->facility_id)->where('code', 'TRI')->first() ?: $destination;
            return $this->createQueue($visit, $triage, $actor, VisitStatus::AwaitingTriage, 'Triage required before OPD');
        }
        return $destination ? $this->createQueue($visit, $destination, $actor, VisitStatus::Waiting, 'Registration completed') : null;
    }

    private function assertSameFacility(Visit $visit, Department $department): void
    {
        if ($visit->facility_id !== $department->facility_id || $visit->facility_id !== currentFacility()?->id) {
            abort(404);
        }
    }

    private function audit($actor, string $event, object $subject, array $values = []): void
    {
        ActivityLog::query()->create(['user_id' => $actor?->id, 'event' => $event, 'subject_type' => $subject::class, 'subject_id' => $subject->id, 'new_values' => $values, 'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent()]);
    }
}
