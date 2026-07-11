<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ObservationAdmission;
use App\Models\ObservationSchedule;

class ObservationScheduleService
{
    public function createSchedule(ObservationAdmission $admission, array $data, $actor): ObservationSchedule { $schedule = ObservationSchedule::query()->create(['facility_id'=>$admission->facility_id,'observation_admission_id'=>$admission->id,'schedule_type'=>$data['schedule_type'],'interval_minutes'=>$data['interval_minutes'] ?? null,'starts_at'=>$data['starts_at'] ?? now(),'ends_at'=>$data['ends_at'] ?? null,'instructions'=>$data['instructions'] ?? null,'next_due_at'=>$this->calculateNextDue($data['starts_at'] ?? now(), $data['interval_minutes'] ?? null),'is_active'=>true,'created_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'observation_schedule_created','subject_type'=>$schedule::class,'subject_id'=>$schedule->id]); return $schedule; }
    public function calculateNextDue($start, ?int $minutes): ?\Illuminate\Support\Carbon { return $minutes ? \Illuminate\Support\Carbon::parse($start)->addMinutes($minutes) : \Illuminate\Support\Carbon::parse($start); }
    public function markCompleted(ObservationSchedule $schedule, $actor): ObservationSchedule { $schedule->update(['next_due_at'=>$this->calculateNextDue(now(), $schedule->interval_minutes), 'updated_by'=>$actor->id]); return $schedule->refresh(); }
    public function getOverdueTasks() { return ObservationSchedule::query()->forCurrentFacility()->where('is_active', true)->whereNotNull('next_due_at')->where('next_due_at','<',now())->get(); }
    public function stopSchedule(ObservationSchedule $schedule, $actor): ObservationSchedule { $schedule->update(['is_active'=>false,'updated_by'=>$actor->id]); return $schedule->refresh(); }
}
