<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\NebulizationRecord;
use App\Models\ObservationAdmission;

class NebulizationService { public function record(ObservationAdmission $a, array $data, $actor): NebulizationRecord { $n = NebulizationRecord::query()->create(['facility_id'=>$a->facility_id,'observation_admission_id'=>$a->id,'patient_id'=>$a->patient_id,'observation_order_id'=>$data['observation_order_id'] ?? null,'medication_details'=>$data['medication_details'],'started_at'=>$data['started_at'] ?? now(),'completed_at'=>$data['completed_at'] ?? now(),'pre_spo2'=>$data['pre_spo2'] ?? null,'post_spo2'=>$data['post_spo2'] ?? null,'pre_respiratory_rate'=>$data['pre_respiratory_rate'] ?? null,'post_respiratory_rate'=>$data['post_respiratory_rate'] ?? null,'administered_by'=>$actor->id,'response'=>$data['response'] ?? null,'adverse_reaction'=>$data['adverse_reaction'] ?? null,'status'=>$data['status'] ?? 'completed','notes'=>$data['notes'] ?? null]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'nebulization_completed','subject_type'=>$n::class,'subject_id'=>$n->id]); return $n; } }
