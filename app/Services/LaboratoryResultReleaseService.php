<?php

namespace App\Services;

use App\Enums\ClinicalOrderStatus;
use App\Enums\LaboratoryResultStatus;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryResultReleaseService
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly VisitClosureService $visitClosure,
    ) {}

    public function release(LaboratoryResult $result, $actor): LaboratoryResult
    {
        return DB::transaction(function () use ($result, $actor) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($result->order->visit_id);
            abort_unless($visit->facility_id === currentFacility()?->id && $result->facility_id === $visit->facility_id, 403);
            $item = $result->orderItem()->lockForUpdate()->firstOrFail();
            if (in_array($item->status, ['cancelled', 'not_performed', 'entered_in_error'], true)) {
                throw ValidationException::withMessages(['result' => 'This test has a terminal decision.']);
            }
            $result = LaboratoryResult::query()->lockForUpdate()->findOrFail($result->id);
            if ($result->result_status === LaboratoryResultStatus::Released) {
                $this->visitClosure->evaluate($visit, $actor);

                return $result->refresh();
            }
            if ($result->result_status !== LaboratoryResultStatus::Verified) {
                throw ValidationException::withMessages(['result' => 'Verified result pekee ndiyo inaweza kutolewa.']);
            }
            $result->update(['result_status' => LaboratoryResultStatus::Released, 'released_by' => $actor->id, 'released_at' => now(), 'updated_by' => $actor->id]);
            $result->orderItem->update(['result_status' => 'released', 'result_released_at' => now(), 'status' => 'completed']);
            $this->updateOrderStatuses($result, $actor);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'result_released', 'subject_type' => $result::class, 'subject_id' => $result->id, 'new_values' => ['facility_id' => $result->facility_id, 'visit_id' => $result->order?->visit_id, 'laboratory_order_id' => $result->laboratory_order_id]]);

            return $result->refresh();
        });
    }

    public function updateOrderStatuses(LaboratoryResult $result, $actor): void
    {
        $order = $result->order;
        $hasUnreleasedItems = $order->items()
            ->whereNotIn('status', ['cancelled', 'not_performed', 'entered_in_error'])
            ->where(fn ($query) => $query
                ->whereNull('result_status')
                ->orWhere('result_status', '!=', LaboratoryResultStatus::Released->value))
            ->exists();
        if (! $hasUnreleasedItems) {
            $order->update(['status' => ClinicalOrderStatus::Completed, 'completed_at' => now()]);
            $visit = $order->visit;
            if (! $visit) {
                return;
            }

            $allVisitOrdersTerminal = LaboratoryOrder::query()
                ->where('visit_id', $visit->id)
                ->whereNotIn('status', [
                    ClinicalOrderStatus::Completed->value,
                    ClinicalOrderStatus::Cancelled->value,
                ])
                ->doesntExist();
            if ($allVisitOrdersTerminal) {
                $this->visitClosure->completeDepartmentQueues($visit, 'LAB', $actor);
            }
            if ($order->isDirectLaboratory() && $allVisitOrdersTerminal) {
                $this->visitClosure->evaluate($visit->refresh(), $actor);

                return;
            }
            $activeEncounter = $visit->activeClinicalEncounter;
            if ($allVisitOrdersTerminal && $activeEncounter) {
                $opdQueue = $visit->queues()
                    ->where('department_id', $activeEncounter->department_id)
                    ->whereIn('queue_status', ['waiting', 'called', 'serving'])
                    ->latest()
                    ->first();
                $this->workflow->updateCurrentDepartment($visit, $activeEncounter->department, $actor, $opdQueue);
                $this->workflow->updateVisitStatus($visit->refresh(), VisitStatus::InConsultation, $actor, $opdQueue);
            }
            $this->visitClosure->evaluate($visit->refresh(), $actor);
        }
    }
}
