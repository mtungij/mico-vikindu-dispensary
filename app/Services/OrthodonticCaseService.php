<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DentalEncounter;
use App\Models\OrthodonticCase;

class OrthodonticCaseService
{
    public function __construct(private readonly DentalEncounterNumberService $numbers) {}
    public function create(DentalEncounter $encounter, array $data, $actor): OrthodonticCase
    {
        $case = OrthodonticCase::query()->create(['facility_id'=>$encounter->facility_id,'patient_id'=>$encounter->patient_id,'dental_encounter_id'=>$encounter->id,'case_number'=>$this->numbers->orthodonticCase($encounter->facility_id),'chief_concern'=>$data['chief_concern'],'diagnosis'=>$data['diagnosis'] ?? null,'malocclusion_class'=>$data['malocclusion_class'] ?? null,'treatment_goal'=>$data['treatment_goal'] ?? null,'appliance_type'=>$data['appliance_type'] ?? null,'treatment_start_date'=>$data['treatment_start_date'] ?? null,'expected_duration_months'=>$data['expected_duration_months'] ?? null,'status'=>$data['status'] ?? 'assessment','assigned_dentist'=>$actor->id,'notes'=>$data['notes'] ?? null,'created_by'=>$actor->id]);
        ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'orthodontic_case_created','subject_type'=>OrthodonticCase::class,'subject_id'=>$case->id,'new_values'=>[]]);
        return $case;
    }
    public function recordVisit(OrthodonticCase $case, array $data, $actor) { $visit = $case->visits()->create([...$data,'provider_user_id'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'orthodontic_visit_recorded','subject_type'=>OrthodonticCase::class,'subject_id'=>$case->id,'new_values'=>[]]); return $visit; }
}
