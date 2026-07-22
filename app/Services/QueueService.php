<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\PatientQueue;
use App\Models\Visit;

class QueueService
{
    public function __construct(private readonly QueueNumberService $numbers, private readonly WorkflowService $workflow) {}

    public function createQueue(Visit $visit, $actor): ?PatientQueue
    {
        $department = $visit->visit_status === VisitStatus::AwaitingTriage
            ? Department::query()
                ->where('facility_id', $visit->facility_id)
                ->where('code', 'TRI')
                ->first()
            : $visit->destinationDepartment;

        if (! $department || ! $department->queue_enabled) {
            return null;
        }

        return $this->workflow->createQueue($visit, $department, $actor, $visit->visit_status);
    }
}
