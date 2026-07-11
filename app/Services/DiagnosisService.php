<?php

namespace App\Services;

use App\Enums\DiagnosisCertainty;
use App\Enums\DiagnosisStatus;
use App\Enums\DiagnosisType;
use App\Models\ActivityLog;
use App\Models\ClinicalEncounter;
use App\Models\Diagnosis;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiagnosisService
{
    public function addDiagnosis(ClinicalEncounter $encounter, array $data, $actor): Diagnosis
    {
        return DB::transaction(function () use ($encounter, $data, $actor) {
            if (($data['is_primary'] ?? false) === true) {
                $this->setPrimaryDiagnosis($encounter, null);
            }
            $diagnosis = Diagnosis::query()->create([
                'facility_id' => $encounter->facility_id,
                'patient_id' => $encounter->patient_id,
                'visit_id' => $encounter->visit_id,
                'clinical_encounter_id' => $encounter->id,
                'diagnosis_type' => $data['diagnosis_type'] ?? DiagnosisType::Provisional,
                'icd10_code' => $data['icd10_code'] ?? null,
                'diagnosis_name' => $data['diagnosis_name'],
                'description' => $data['description'] ?? null,
                'certainty' => $data['certainty'] ?? DiagnosisCertainty::Suspected,
                'is_primary' => (bool) ($data['is_primary'] ?? false),
                'diagnosed_by' => $actor->id,
                'diagnosed_at' => now(),
                'status' => $data['status'] ?? DiagnosisStatus::Active,
                'created_by' => $actor->id,
            ]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'diagnosis_added', 'subject_type' => $diagnosis::class, 'subject_id' => $diagnosis->id]);
            return $diagnosis;
        });
    }

    public function setPrimaryDiagnosis(ClinicalEncounter $encounter, ?Diagnosis $diagnosis): void
    {
        Diagnosis::query()->where('clinical_encounter_id', $encounter->id)->update(['is_primary' => false]);
        if ($diagnosis) {
            $diagnosis->update(['is_primary' => true]);
        }
    }

    public function markEnteredInError(Diagnosis $diagnosis, string $reason, $actor): Diagnosis
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu inahitajika.']);
        }
        $diagnosis->update(['status' => DiagnosisStatus::EnteredInError, 'error_reason' => $reason, 'updated_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'diagnosis_marked_error', 'subject_type' => $diagnosis::class, 'subject_id' => $diagnosis->id]);
        return $diagnosis->refresh();
    }

    public function validateCompletionDiagnosis(ClinicalEncounter $encounter): void
    {
        $hasFinal = $encounter->diagnoses()->whereIn('diagnosis_type', [DiagnosisType::Final->value, DiagnosisType::Confirmed->value])->where('status', '!=', DiagnosisStatus::EnteredInError->value)->exists();
        if (! $hasFinal) {
            throw ValidationException::withMessages(['diagnosis' => 'Diagnosis ya mwisho inahitajika kabla ya kukamilisha consultation.']);
        }
    }
}
