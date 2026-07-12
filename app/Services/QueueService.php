<?php

namespace App\Services;

use App\Models\PatientQueue;
use App\Models\Visit;

class QueueService
{
    public function __construct(private readonly QueueNumberService $numbers, private readonly WorkflowService $workflow) {}

    public function createQueue(Visit $visit, $actor): ?PatientQueue
    {
        $department = $visit->destinationDepartment;
        if (! $department || ! $department->queue_enabled) {
            return null;
        }

        return $this->workflow->createQueue($visit, $department, $actor);
    }
}
