<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DentalEncounter;
use App\Models\DentalDiagnosis;
use Illuminate\Support\Facades\DB;

class DentalDiagnosisService
{
    public function add(DentalEncounter $encounter, array $data, $actor): DentalDiagnosis
    {
        abort_unless($encounter->facility_id === currentFacility()?->id, 404);
        return DB::transaction(function () use ($encounter, $data, $actor) {
            if ($data['is_primary'] ?? false) $encounter->diagnoses()->update(['is_primary'=>false]);
            $diagnosis = $encounter->diagnoses()->create(['facility_id'=>$encounter->facility_id,'patient_id'=>$encounter->patient_id,'visit_id'=>$encounter->visit_id,'tooth_number'=>$data['tooth_number'] ?? null,'surface'=>$data['surface'] ?? null,'diagnosis_type'=>$data['diagnosis_type'] ?? 'dental','diagnosis_name'=>$data['diagnosis_name'],'icd10_code'=>$data['icd10_code'] ?? null,'certainty'=>$data['certainty'] ?? 'provisional','is_primary'=>$data['is_primary'] ?? false,'status'=>$data['status'] ?? 'active','diagnosed_by'=>$actor->id,'diagnosed_at'=>now(),'notes'=>$data['notes'] ?? null,'created_by'=>$actor->id]);
            ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'dental_diagnosis_added','subject_type'=>DentalDiagnosis::class,'subject_id'=>$diagnosis->id,'new_values'=>['encounter_id'=>$encounter->id]]);
            return $diagnosis;
        });
    }
}
