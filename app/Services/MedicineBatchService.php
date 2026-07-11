<?php

namespace App\Services;

use App\Enums\MedicineBatchStatus;
use App\Models\ActivityLog;
use App\Models\MedicineBatch;

class MedicineBatchService
{
    public function quarantine(MedicineBatch $batch, $actor, string $reason): MedicineBatch { $batch->update(['status' => MedicineBatchStatus::Quarantined, 'updated_by' => $actor->id]); ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'batch_quarantined', 'subject_type' => $batch::class, 'subject_id' => $batch->id, 'new_values' => ['reason' => $reason]]); return $batch->refresh(); }
    public function recall(MedicineBatch $batch, $actor, string $reason): MedicineBatch { $batch->update(['status' => MedicineBatchStatus::Recalled, 'updated_by' => $actor->id]); ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'batch_recalled', 'subject_type' => $batch::class, 'subject_id' => $batch->id, 'new_values' => ['reason' => $reason]]); return $batch->refresh(); }
}
