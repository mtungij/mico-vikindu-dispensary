<?php

namespace App\Services;

use App\Enums\IvFluidStatus;
use App\Models\ActivityLog;
use App\Models\IvFluidAdministration;
use App\Models\ObservationAdmission;

class IvFluidService { public function start(ObservationAdmission $a, array $data, $actor): IvFluidAdministration { $iv = IvFluidAdministration::query()->create(['facility_id'=>$a->facility_id,'observation_admission_id'=>$a->id,'patient_id'=>$a->patient_id,'visit_id'=>$a->visit_id,'observation_order_id'=>$data['observation_order_id'] ?? null,'prescription_item_id'=>$data['prescription_item_id'] ?? null,'medicine_id'=>$data['medicine_id'] ?? null,'fluid_name_snapshot'=>$data['fluid_name_snapshot'],'volume_ml'=>$data['volume_ml'],'rate_ml_per_hour'=>$data['rate_ml_per_hour'] ?? null,'drops_per_minute'=>$data['drops_per_minute'] ?? null,'route'=>$data['route'] ?? 'IV','started_at'=>$data['started_at'] ?? now(),'expected_end_at'=>$data['expected_end_at'] ?? null,'started_by'=>$actor->id,'status'=>IvFluidStatus::Running,'remaining_volume_ml'=>$data['remaining_volume_ml'] ?? $data['volume_ml'],'cannula_site'=>$data['cannula_site'] ?? null,'notes'=>$data['notes'] ?? null,'created_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'iv_fluid_started','subject_type'=>$iv::class,'subject_id'=>$iv->id]); return $iv; } public function complete(IvFluidAdministration $iv, $actor, ?string $notes = null): IvFluidAdministration { $iv->update(['status'=>IvFluidStatus::Completed,'completed_at'=>now(),'completed_by'=>$actor->id,'remaining_volume_ml'=>0,'notes'=>$notes ?? $iv->notes,'updated_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'iv_fluid_completed','subject_type'=>$iv::class,'subject_id'=>$iv->id]); return $iv->refresh(); } }
