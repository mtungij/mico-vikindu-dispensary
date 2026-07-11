<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ObservationAdmission;
use App\Models\OxygenTherapyRecord;

class OxygenTherapyService { public function start(ObservationAdmission $a, array $data, $actor): OxygenTherapyRecord { $o = OxygenTherapyRecord::query()->create(['facility_id'=>$a->facility_id,'observation_admission_id'=>$a->id,'patient_id'=>$a->patient_id,'observation_order_id'=>$data['observation_order_id'] ?? null,'delivery_method'=>$data['delivery_method'],'flow_rate_lpm'=>$data['flow_rate_lpm'] ?? null,'target_spo2'=>$data['target_spo2'] ?? null,'started_at'=>$data['started_at'] ?? now(),'started_by'=>$actor->id,'status'=>'active','pre_spo2'=>$data['pre_spo2'] ?? null,'notes'=>$data['notes'] ?? null]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'oxygen_started','subject_type'=>$o::class,'subject_id'=>$o->id]); return $o; } public function stop(OxygenTherapyRecord $o, $actor, ?float $postSpo2 = null): OxygenTherapyRecord { $o->update(['status'=>'completed','ended_at'=>now(),'ended_by'=>$actor->id,'post_spo2'=>$postSpo2]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'oxygen_stopped','subject_type'=>$o::class,'subject_id'=>$o->id]); return $o->refresh(); } }
