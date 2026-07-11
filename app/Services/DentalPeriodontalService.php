<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DentalEncounter;
use App\Models\PeriodontalAssessment;
use Illuminate\Validation\ValidationException;

class DentalPeriodontalService
{
    public function record(DentalEncounter $encounter, array $data, array $measurements, $actor): PeriodontalAssessment
    {
        foreach (['plaque_index','bleeding_index','calculus_index'] as $field) if (isset($data[$field]) && ((float)$data[$field] < 0 || (float)$data[$field] > 100)) throw ValidationException::withMessages([$field=>'Index lazima iwe 0 mpaka 100.']);
        $assessment = PeriodontalAssessment::query()->create(['facility_id'=>$encounter->facility_id,'dental_encounter_id'=>$encounter->id,'patient_id'=>$encounter->patient_id,'assessment_date'=>$data['assessment_date'] ?? today(),'plaque_index'=>$data['plaque_index'] ?? null,'bleeding_index'=>$data['bleeding_index'] ?? null,'calculus_index'=>$data['calculus_index'] ?? null,'oral_hygiene_status'=>$data['oral_hygiene_status'] ?? null,'gingival_status'=>$data['gingival_status'] ?? null,'periodontal_diagnosis'=>$data['periodontal_diagnosis'] ?? null,'recorded_by'=>$actor->id,'notes'=>$data['notes'] ?? null]);
        foreach ($measurements as $row) { if (isset($row['pocket_depth_mm']) && (float)$row['pocket_depth_mm'] > 15) throw ValidationException::withMessages(['pocket_depth_mm'=>'Pocket depth si sahihi.']); $assessment->measurements()->create($row); }
        ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'periodontal_assessment_recorded','subject_type'=>PeriodontalAssessment::class,'subject_id'=>$assessment->id,'new_values'=>[]]);
        return $assessment;
    }
}
