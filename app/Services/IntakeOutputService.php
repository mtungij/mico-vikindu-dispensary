<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\IntakeOutputRecord;
use App\Models\ObservationAdmission;

class IntakeOutputService { public function record(ObservationAdmission $a, array $data, $actor): IntakeOutputRecord { $r = IntakeOutputRecord::query()->create(['facility_id'=>$a->facility_id,'observation_admission_id'=>$a->id,'patient_id'=>$a->patient_id,'recorded_by'=>$actor->id,'recorded_at'=>$data['recorded_at'] ?? now(),'record_type'=>$data['record_type'],'route_or_source'=>$data['route_or_source'] ?? null,'description'=>$data['description'] ?? null,'volume_ml'=>$data['volume_ml'],'notes'=>$data['notes'] ?? null]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'intake_output_recorded','subject_type'=>$r::class,'subject_id'=>$r->id]); return $r; } public function balance(ObservationAdmission $a, ?\DateTimeInterface $from = null): float { $q = IntakeOutputRecord::query()->where('observation_admission_id',$a->id)->when($from, fn($x)=>$x->where('recorded_at','>=',$from)); $in = (clone $q)->where('record_type','intake')->sum('volume_ml'); $out = (clone $q)->where('record_type','!=','intake')->sum('volume_ml'); return (float) $in - (float) $out; } }
