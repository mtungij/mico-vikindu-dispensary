<?php

namespace App\Services;

use App\Enums\ClinicalAlertStatus;
use App\Models\ActivityLog;
use App\Models\ClinicalAlert;
use App\Models\NursingObservation;
use App\Models\ObservationAdmission;

class NursingObservationService
{
    public function __construct(private readonly VitalSignAssessmentService $vitals) {}
    public function record(ObservationAdmission $admission, array $data, $actor): NursingObservation
    {
        $payload = [...$data, 'facility_id'=>$admission->facility_id,'observation_admission_id'=>$admission->id,'patient_id'=>$admission->patient_id,'visit_id'=>$admission->visit_id,'recorded_by'=>$actor->id,'recorded_at'=>$data['recorded_at'] ?? now(),'status'=>$data['status'] ?? 'completed','created_by'=>$actor->id];
        $this->vitals->validateVitalRanges($payload);
        $observation = NursingObservation::query()->create($payload);
        foreach ($this->vitals->buildClinicalAlerts($payload) as $alert) {
            ClinicalAlert::query()->create(['facility_id'=>$admission->facility_id,'patient_id'=>$admission->patient_id,'visit_id'=>$admission->visit_id,'alert_type'=>'observation_abnormal_vital','severity'=>$alert['severity'],'title'=>$alert['title'],'message'=>$alert['message'],'source_type'=>$observation::class,'source_id'=>$observation->id,'status'=>ClinicalAlertStatus::Active]);
        }
        ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'nursing_observation_recorded','subject_type'=>$observation::class,'subject_id'=>$observation->id]);
        return $observation;
    }
    public function amend(NursingObservation $observation, array $data, $actor, string $reason): NursingObservation { if (blank($reason)) throw \Illuminate\Validation\ValidationException::withMessages(['reason'=>'Sababu ya amendment inahitajika.']); $observation->update([...$data,'status'=>'amended','updated_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'nursing_observation_amended','subject_type'=>$observation::class,'subject_id'=>$observation->id]); return $observation->refresh(); }
}
