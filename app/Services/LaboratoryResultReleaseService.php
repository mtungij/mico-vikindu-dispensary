<?php

namespace App\Services;

use App\Enums\ClinicalEncounterStatus;
use App\Enums\ClinicalOrderStatus;
use App\Enums\LaboratoryResultStatus;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\LaboratoryResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryResultReleaseService
{
    public function release(LaboratoryResult $result, $actor): LaboratoryResult
    {
        return DB::transaction(function () use ($result, $actor) {
            $result = LaboratoryResult::query()->lockForUpdate()->findOrFail($result->id);
            if ($result->result_status !== LaboratoryResultStatus::Verified) {
                throw ValidationException::withMessages(['result' => 'Verified result pekee ndiyo inaweza kutolewa.']);
            }
            $result->update(['result_status' => LaboratoryResultStatus::Released, 'released_by' => $actor->id, 'released_at' => now(), 'updated_by' => $actor->id]);
            $result->orderItem->update(['result_status' => 'released', 'result_released_at' => now(), 'status' => 'completed']);
            $this->updateOrderStatuses($result);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'result_released', 'subject_type' => $result::class, 'subject_id' => $result->id]);
            return $result->refresh();
        });
    }

    public function updateOrderStatuses(LaboratoryResult $result): void
    {
        $order = $result->order;
        if ($order->items()->where('result_status', '!=', 'released')->doesntExist()) {
            $order->update(['status' => ClinicalOrderStatus::Completed, 'completed_at' => now()]);
            $order->visit?->update(['visit_status' => VisitStatus::AwaitingDepartment]);
            $order->encounter?->update(['status' => ClinicalEncounterStatus::AwaitingResults]);
        }
    }
}
