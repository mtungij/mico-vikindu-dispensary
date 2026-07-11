<?php

namespace App\Services;

use App\Enums\BedCleaningStatus;
use App\Enums\BedStatus;
use App\Models\ActivityLog;
use App\Models\BedCleaningRecord;

class BedCleaningService { public function start(BedCleaningRecord $r, $actor): BedCleaningRecord { $r->update(['status'=>'in_progress','started_at'=>now(),'cleaned_by'=>$actor->id]); $r->bed->update(['status'=>BedStatus::Cleaning,'current_cleaning_status'=>BedCleaningStatus::CleaningInProgress]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'bed_cleaning_started','subject_type'=>$r::class,'subject_id'=>$r->id]); return $r->refresh(); } public function complete(BedCleaningRecord $r, $actor, bool $makeAvailable = true): BedCleaningRecord { $r->update(['status'=>'completed','completed_at'=>now(),'cleaned_by'=>$actor->id]); $r->bed->update(['status'=>$makeAvailable ? BedStatus::Available : BedStatus::Cleaning,'current_cleaning_status'=>$makeAvailable ? BedCleaningStatus::Clean : BedCleaningStatus::Disinfected,'last_cleaned_at'=>now()]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'bed_cleaning_completed','subject_type'=>$r::class,'subject_id'=>$r->id]); return $r->refresh(); } }
