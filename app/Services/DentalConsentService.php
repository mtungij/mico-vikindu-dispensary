<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DentalConsent;
use App\Models\DentalEncounter;
use Illuminate\Validation\ValidationException;

class DentalConsentService
{
    public function create(DentalEncounter $encounter, array $data, $actor): DentalConsent
    {
        $consent = DentalConsent::query()->create(['facility_id'=>$encounter->facility_id,'patient_id'=>$encounter->patient_id,'visit_id'=>$encounter->visit_id,'dental_encounter_id'=>$encounter->id,'dental_procedure_id'=>$data['dental_procedure_id'] ?? null,'consent_type'=>$data['consent_type'],'consent_text_snapshot'=>$data['consent_text_snapshot'],'risks_explained'=>$data['risks_explained'] ?? null,'alternatives_explained'=>$data['alternatives_explained'] ?? null,'patient_or_guardian_name'=>$data['patient_or_guardian_name'],'relationship_to_patient'=>$data['relationship_to_patient'] ?? null,'consent_given'=>$data['consent_given'] ?? false,'signed_at'=>($data['consent_given'] ?? false) ? now() : null,'patient_signature_path'=>$data['patient_signature_path'] ?? null,'witness_user_id'=>$data['witness_user_id'] ?? null,'clinician_user_id'=>$actor->id]);
        ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'dental_consent_created','subject_type'=>DentalConsent::class,'subject_id'=>$consent->id,'new_values'=>['type'=>$consent->consent_type]]);
        return $consent;
    }
    public function update(DentalConsent $consent, array $data): void { if ($consent->signed_at) throw ValidationException::withMessages(['consent'=>'Signed consent haiwezi kubadilishwa.']); $consent->update($data); }
}
