<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DentalEncounter;
use App\Models\DentalLabOrder;

class DentalLabOrderService
{
    public function __construct(private readonly DentalEncounterNumberService $numbers) {}
    public function create(DentalEncounter $encounter, array $data, $actor): DentalLabOrder
    {
        $order = DentalLabOrder::query()->create(['facility_id'=>$encounter->facility_id,'patient_id'=>$encounter->patient_id,'visit_id'=>$encounter->visit_id,'dental_encounter_id'=>$encounter->id,'treatment_plan_item_id'=>$data['treatment_plan_item_id'] ?? null,'order_number'=>$this->numbers->labOrder($encounter->facility_id),'work_type'=>$data['work_type'],'tooth_numbers'=>$data['tooth_numbers'] ?? null,'shade'=>$data['shade'] ?? null,'material'=>$data['material'] ?? null,'design_instructions'=>$data['design_instructions'] ?? null,'external_lab_name'=>$data['external_lab_name'] ?? null,'external_reference'=>$data['external_reference'] ?? null,'expected_at'=>$data['expected_at'] ?? null,'status'=>$data['status'] ?? 'draft','ordered_by'=>$actor->id,'created_by'=>$actor->id]);
        ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'dental_lab_order_created','subject_type'=>DentalLabOrder::class,'subject_id'=>$order->id,'new_values'=>[]]);
        return $order;
    }
}
