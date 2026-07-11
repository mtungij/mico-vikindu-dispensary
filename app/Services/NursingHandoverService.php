<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\NursingHandover;
use App\Models\ObservationAdmission;

class NursingHandoverService { public function create(ObservationAdmission $a, array $data, $actor): NursingHandover { $h = NursingHandover::query()->create(['facility_id'=>$a->facility_id,'observation_admission_id'=>$a->id,'patient_id'=>$a->patient_id,'from_user_id'=>$actor->id,'to_user_id'=>$data['to_user_id'] ?? null,'shift_name'=>$data['shift_name'] ?? null,'handover_at'=>$data['handover_at'] ?? now(),'patient_condition'=>$data['patient_condition'],'pending_medications'=>$data['pending_medications'] ?? null,'pending_orders'=>$data['pending_orders'] ?? null,'iv_fluids_status'=>$data['iv_fluids_status'] ?? null,'critical_alerts'=>$data['critical_alerts'] ?? null,'special_instructions'=>$data['special_instructions'] ?? null,'next_observation_due_at'=>$data['next_observation_due_at'] ?? null,'referral_status'=>$data['referral_status'] ?? null,'discharge_plan'=>$data['discharge_plan'] ?? null]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'handover_created','subject_type'=>$h::class,'subject_id'=>$h->id]); return $h; } public function acknowledge(NursingHandover $h, $actor): NursingHandover { $h->update(['acknowledged_by'=>$actor->id,'acknowledged_at'=>now()]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'handover_acknowledged','subject_type'=>$h::class,'subject_id'=>$h->id]); return $h->refresh(); } }
