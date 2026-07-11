<?php

namespace App\Services;

use App\Enums\NursingTaskStatus;
use App\Models\ActivityLog;
use App\Models\NursingTask;
use App\Models\ObservationAdmission;

class NursingTaskService { public function create(ObservationAdmission $a, array $data, $actor): NursingTask { $t = NursingTask::query()->create(['facility_id'=>$a->facility_id,'observation_admission_id'=>$a->id,'patient_id'=>$a->patient_id,'observation_order_id'=>$data['observation_order_id'] ?? null,'task_type'=>$data['task_type'],'title'=>$data['title'],'description'=>$data['description'] ?? null,'priority'=>$data['priority'] ?? 'routine','due_at'=>$data['due_at'] ?? null,'assigned_to_user_id'=>$data['assigned_to_user_id'] ?? null,'status'=>NursingTaskStatus::Pending,'created_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'nursing_task_created','subject_type'=>$t::class,'subject_id'=>$t->id]); return $t; } public function complete(NursingTask $t, $actor, ?string $notes = null): NursingTask { $t->update(['status'=>NursingTaskStatus::Completed,'completed_at'=>now(),'completed_by'=>$actor->id,'notes'=>$notes,'updated_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'nursing_task_completed','subject_type'=>$t::class,'subject_id'=>$t->id]); return $t->refresh(); } public function overdue() { return NursingTask::query()->forCurrentFacility()->whereNotNull('due_at')->where('due_at','<',now())->whereNotIn('status',['completed','cancelled'])->get(); } }
