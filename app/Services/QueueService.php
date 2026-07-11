<?php

namespace App\Services;

use App\Models\PatientQueue;
use App\Models\Visit;

class QueueService
{
    public function __construct(private readonly QueueNumberService $numbers) {}

    public function createQueue(Visit $visit, $actor): ?PatientQueue
    {
        $department = $visit->destinationDepartment;
        if (! $department || ! $department->queue_enabled) {
            return null;
        }

        return PatientQueue::query()->create([
            'facility_id' => $visit->facility_id,
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'department_id' => $department->id,
            'queue_number' => $this->numbers->next($visit->facility_id, $department),
            'queue_date' => today(),
            'queue_status' => 'waiting',
            'priority' => $visit->priority,
            'created_by' => $actor->id,
        ]);
    }
}
