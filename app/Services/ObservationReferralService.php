<?php

namespace App\Services;

use App\Enums\ObservationAdmissionStatus;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\ObservationAdmission;

class ObservationReferralService { public function refer(ObservationAdmission $a, $actor): ObservationAdmission { $a->update(['status'=>ObservationAdmissionStatus::Referred,'actual_discharge_at'=>now(),'updated_by'=>$actor->id]); $a->visit->update(['visit_status'=>VisitStatus::Referred,'completed_at'=>now()]); app(BedManagementService::class)->releaseBed($a,$actor); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'observation_referred','subject_type'=>$a::class,'subject_id'=>$a->id]); return $a->refresh(); } }
