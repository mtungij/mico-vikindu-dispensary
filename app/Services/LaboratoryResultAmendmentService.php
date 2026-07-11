<?php

namespace App\Services;

use App\Enums\LaboratoryResultStatus;
use App\Models\ActivityLog;
use App\Models\LaboratoryResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryResultAmendmentService
{
    public function amend(LaboratoryResult $result, array $values, string $reason, $actor): LaboratoryResult
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu ya amendment inahitajika.']);
        }
        return DB::transaction(function () use ($result, $values, $reason, $actor) {
            $old = LaboratoryResult::query()->lockForUpdate()->findOrFail($result->id);
            $old->update(['result_status' => LaboratoryResultStatus::Amended, 'amendment_reason' => $reason, 'updated_by' => $actor->id]);
            $new = $old->replicate(['verified_by', 'verified_at', 'released_by', 'released_at', 'reviewed_by_clinician', 'reviewed_at']);
            $new->result_version = $old->result_version + 1;
            $new->result_status = LaboratoryResultStatus::Draft;
            $new->supersedes_result_id = $old->id;
            $new->amendment_reason = $reason;
            $new->created_by = $actor->id;
            $new->updated_by = null;
            $new->save();
            app(LaboratoryResultService::class)->saveValues($new, $values, $actor, false);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'result_amended', 'subject_type' => $new::class, 'subject_id' => $new->id]);
            return $new->refresh();
        });
    }
}
