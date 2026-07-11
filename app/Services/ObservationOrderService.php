<?php

namespace App\Services;

use App\Enums\ObservationOrderStatus;
use App\Models\ActivityLog;
use App\Models\ObservationAdmission;
use App\Models\ObservationOrder;
use Illuminate\Validation\ValidationException;

class ObservationOrderService
{
    public function create(ObservationAdmission $admission, array $data, $actor): ObservationOrder { $order = ObservationOrder::query()->create(['facility_id'=>$admission->facility_id,'observation_admission_id'=>$admission->id,'patient_id'=>$admission->patient_id,'visit_id'=>$admission->visit_id,'clinical_encounter_id'=>$data['clinical_encounter_id'] ?? $admission->clinical_encounter_id,'order_type'=>$data['order_type'],'priority'=>$data['priority'] ?? 'routine','instructions'=>$data['instructions'],'ordered_by'=>$actor->id,'ordered_at'=>$data['ordered_at'] ?? now(),'scheduled_at'=>$data['scheduled_at'] ?? null,'status'=>ObservationOrderStatus::Pending,'metadata'=>$data['metadata'] ?? null,'created_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'observation_order_created','subject_type'=>$order::class,'subject_id'=>$order->id]); return $order; }
    public function acknowledge(ObservationOrder $order, $actor): ObservationOrder { $order->update(['status'=>ObservationOrderStatus::Acknowledged,'started_at'=>now(),'updated_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'observation_order_acknowledged','subject_type'=>$order::class,'subject_id'=>$order->id]); return $order->refresh(); }
    public function complete(ObservationOrder $order, $actor): ObservationOrder { $order->update(['status'=>ObservationOrderStatus::Completed,'completed_at'=>now(),'completed_by'=>$actor->id,'updated_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'observation_order_completed','subject_type'=>$order::class,'subject_id'=>$order->id]); return $order->refresh(); }
    public function cancel(ObservationOrder $order, $actor, string $reason): ObservationOrder { if (blank($reason)) throw ValidationException::withMessages(['reason'=>'Sababu inahitajika.']); $order->update(['status'=>ObservationOrderStatus::Cancelled,'cancelled_at'=>now(),'cancelled_by'=>$actor->id,'cancellation_reason'=>$reason,'updated_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'observation_order_cancelled','subject_type'=>$order::class,'subject_id'=>$order->id]); return $order->refresh(); }
}
