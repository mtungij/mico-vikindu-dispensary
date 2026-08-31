<?php

namespace App\Services;

use App\Enums\DiagnosisCertainty;
use App\Enums\DiagnosisStatus;
use App\Enums\DiagnosisType;
use App\Models\ActivityLog;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalNoteAmendment;
use App\Models\Diagnosis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DiagnosisService
{
    public function addDiagnosis(ClinicalEncounter $encounter, array $data, $actor): Diagnosis
    {
        return DB::transaction(function () use ($encounter, $data, $actor) {
            $encounter = ClinicalEncounter::query()->with('visit')->lockForUpdate()->findOrFail($encounter->id);
            Gate::forUser($actor)->authorize('create', Diagnosis::class);
            $this->ensureEncounterMutable($encounter, $actor);
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

    public function assertEditable(Diagnosis $diagnosis, $actor): void
    {
        $diagnosis->loadMissing('encounter.visit');
        Gate::forUser($actor)->authorize('update', $diagnosis);
        $this->ensureEncounterMutable($diagnosis->encounter, $actor);
    }

    public function updateDiagnosis(Diagnosis $diagnosis, array $data, $actor): Diagnosis
    {
        return DB::transaction(function () use ($diagnosis, $data, $actor): Diagnosis {
            $diagnosis = Diagnosis::query()->with('encounter.visit')->lockForUpdate()->findOrFail($diagnosis->id);
            $this->assertEditable($diagnosis, $actor);
            $old = $this->clinicalValues($diagnosis);
            if (($data['is_primary'] ?? false) === true) {
                $this->setPrimaryDiagnosis($diagnosis->encounter, $diagnosis);
            }
            $diagnosis->update([...$this->normalizedData($data), 'updated_by' => $actor->id]);
            $this->audit($actor, 'diagnosis_updated', $diagnosis, $old, $this->clinicalValues($diagnosis->refresh()));

            return $diagnosis->refresh();
        });
    }

    public function removeDiagnosis(Diagnosis $diagnosis, $actor): void
    {
        DB::transaction(function () use ($diagnosis, $actor): void {
            $diagnosis = Diagnosis::query()->with('encounter.visit')->lockForUpdate()->findOrFail($diagnosis->id);
            $this->assertEditable($diagnosis, $actor);
            $old = $this->clinicalValues($diagnosis);
            $diagnosis->delete();
            $this->audit($actor, 'diagnosis_removed', $diagnosis, $old, []);
        });
    }

    public function amendDiagnosis(Diagnosis $diagnosis, array $data, string $reason, $actor): Diagnosis
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['diagnosisAmendmentReason' => 'A reason is required to amend a completed diagnosis.']);
        }

        return DB::transaction(function () use ($diagnosis, $data, $reason, $actor): Diagnosis {
            $diagnosis = Diagnosis::query()->with('encounter.visit')->lockForUpdate()->findOrFail($diagnosis->id);
            abort_unless($diagnosis->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);
            Gate::forUser($actor)->authorize('update', $diagnosis);
            abort_unless($actor->can('clinical-encounters.amend'), 403);
            if (! $diagnosis->encounter->isReadOnly()) {
                throw ValidationException::withMessages(['diagnosis' => 'Use the normal Edit action while the consultation is active.']);
            }

            $old = $this->clinicalValues($diagnosis);
            if (($data['is_primary'] ?? false) === true) {
                $this->setPrimaryDiagnosis($diagnosis->encounter, $diagnosis);
            }
            $diagnosis->update([...$this->normalizedData($data), 'updated_by' => $actor->id]);
            $new = $this->clinicalValues($diagnosis->refresh());
            ClinicalNoteAmendment::query()->create([
                'clinical_encounter_id' => $diagnosis->clinical_encounter_id,
                'field_name' => 'diagnosis:'.$diagnosis->id,
                'old_value' => json_encode($old, JSON_THROW_ON_ERROR),
                'new_value' => json_encode($new, JSON_THROW_ON_ERROR),
                'reason' => $reason,
                'amended_by' => $actor->id,
                'amended_at' => now(),
                'created_at' => now(),
            ]);
            $this->audit($actor, 'diagnosis_amended', $diagnosis, $old, [...$new, 'amendment_reason' => $reason]);

            return $diagnosis->refresh();
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

    private function normalizedData(array $data): array
    {
        return collect($data)->only(['diagnosis_type', 'icd10_code', 'diagnosis_name', 'description', 'certainty', 'is_primary'])->all();
    }

    private function clinicalValues(Diagnosis $diagnosis): array
    {
        return $diagnosis->only(['diagnosis_type', 'icd10_code', 'diagnosis_name', 'description', 'certainty', 'is_primary', 'status']);
    }

    private function ensureEncounterMutable(ClinicalEncounter $encounter, $actor): void
    {
        abort_unless($encounter->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);
        if ($encounter->isReadOnly()) {
            throw ValidationException::withMessages(['diagnosis' => 'This diagnosis can no longer be edited because the consultation has been completed.']);
        }
    }

    private function audit($actor, string $event, Diagnosis $diagnosis, array $old = [], array $new = []): void
    {
        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => $event,
            'subject_type' => $diagnosis::class,
            'subject_id' => $diagnosis->id,
            'old_values' => $old,
            'new_values' => ['visit_id' => $diagnosis->visit_id, 'clinical_encounter_id' => $diagnosis->clinical_encounter_id, ...$new],
        ]);
    }

    public function validateCompletionDiagnosis(ClinicalEncounter $encounter): void
    {
        $hasFinal = $encounter->diagnoses()->whereIn('diagnosis_type', [DiagnosisType::Final->value, DiagnosisType::Confirmed->value])->where('status', '!=', DiagnosisStatus::EnteredInError->value)->exists();
        if (! $hasFinal) {
            throw ValidationException::withMessages(['diagnosis' => 'Diagnosis ya mwisho inahitajika kabla ya kukamilisha consultation.']);
        }
    }
}
