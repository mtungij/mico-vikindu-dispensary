<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ObservationAdmission;
use App\Models\ObservationClinicalReview;

class ObservationClinicalReviewService { public function complete(ObservationAdmission $a, array $data, $actor): ObservationClinicalReview { $r = ObservationClinicalReview::query()->create(['facility_id'=>$a->facility_id,'observation_admission_id'=>$a->id,'patient_id'=>$a->patient_id,'visit_id'=>$a->visit_id,'reviewed_by'=>$actor->id,'reviewed_at'=>$data['reviewed_at'] ?? now(),'current_condition'=>$data['current_condition'],'examination_findings'=>$data['examination_findings'] ?? null,'diagnosis_update'=>$data['diagnosis_update'] ?? null,'treatment_plan'=>$data['treatment_plan'] ?? null,'continue_observation'=>(bool)($data['continue_observation'] ?? true),'ready_for_discharge'=>(bool)($data['ready_for_discharge'] ?? false),'referral_required'=>(bool)($data['referral_required'] ?? false),'next_review_at'=>$data['next_review_at'] ?? null,'notes'=>$data['notes'] ?? null,'status'=>'completed','created_by'=>$actor->id]); if ($r->ready_for_discharge) $a->update(['status'=>'ready_for_discharge','updated_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'clinical_review_completed','subject_type'=>$r::class,'subject_id'=>$r->id]); return $r; } }
