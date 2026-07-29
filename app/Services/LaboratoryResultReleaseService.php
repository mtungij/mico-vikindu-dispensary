<?php

namespace App\Services;

use App\Enums\ClinicalOrderStatus;
use App\Enums\LaboratoryResultStatus;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;
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
            $result = LaboratoryResult::query()->lockForUpdate()->findOrFail($result->id);
            if ($result->result_status === LaboratoryResultStatus::Released) {
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
            ->whereNotIn('status', ['cancelled', 'entered_in_error'])
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
            if ($allVisitOrdersTerminal && $this->visitClosure->requiresDoctorReview($visit)) {
                $opd = Department::query()
                    ->where('facility_id', $visit->facility_id)
                    ->where('code', 'OPD')
                    ->where('is_active', true)
                    ->where('can_receive_patients', true)
                    ->where('queue_enabled', true)
                    ->first();
                if (! $opd) {
                    throw ValidationException::withMessages([
                        'destination' => 'Matokeo hayawezi kutolewa kwa sababu OPD review destination haijawekwa vizuri.',
                    ]);
                }
                $this->workflow->createQueue(
                    $visit->refresh(),
                    $opd,
                    $actor,
                    VisitStatus::AwaitingDoctorReview,
                    'Laboratory results released for required doctor review',
                    true,
                );
            }

            $this->visitClosure->evaluate($visit->refresh(), $actor);
        }
    }
}
