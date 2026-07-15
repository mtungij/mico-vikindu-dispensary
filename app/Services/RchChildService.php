<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Patient;
use App\Models\PatientRelationship;
use App\Models\RchChild;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RchChildService
{
    public function __construct(private readonly RchEncounterNumberService $numbers) {}

    public function register(Patient $child, array $data, $actor): RchChild
    {
        return DB::transaction(function () use ($child, $data, $actor): RchChild {
            $facilityId = currentFacility()?->id ?? $child->facility_id;
            if (RchChild::query()->where('child_patient_id', $child->id)->exists()) {
                throw ValidationException::withMessages(['child_patient_id' => 'Mtoto huyu tayari amesajiliwa RCH.']);
            }
            $rchChild = RchChild::query()->create([
                'facility_id' => $facilityId,
                'child_patient_id' => $child->id,
                'mother_patient_id' => $data['mother_patient_id'] ?? null,
                'father_patient_id' => $data['father_patient_id'] ?? null,
                'guardian_patient_id' => $data['guardian_patient_id'] ?? null,
                'child_rch_number' => $this->numbers->child($facilityId),
                'birth_registration_number' => $data['birth_registration_number'] ?? null,
                'birth_date' => $data['birth_date'] ?? $child->date_of_birth ?? today(),
                'birth_time' => $data['birth_time'] ?? null,
                'birth_place' => $data['birth_place'] ?? null,
                'birth_type' => $data['birth_type'] ?? null,
                'birth_order' => $data['birth_order'] ?? null,
                'gestational_age_at_birth_weeks' => $data['gestational_age_at_birth_weeks'] ?? null,
                'birth_weight_kg' => $data['birth_weight_kg'] ?? null,
                'birth_length_cm' => $data['birth_length_cm'] ?? null,
                'head_circumference_at_birth_cm' => $data['head_circumference_at_birth_cm'] ?? null,
                'sex_at_birth' => $data['sex_at_birth'] ?? ($child->gender?->value ?? $child->gender ?? 'unknown'),
                'delivery_mode' => $data['delivery_mode'] ?? null,
                'apgar_1_minute' => $data['apgar_1_minute'] ?? null,
                'apgar_5_minutes' => $data['apgar_5_minutes'] ?? null,
                'neonatal_complications' => $data['neonatal_complications'] ?? null,
                'feeding_method' => $data['feeding_method'] ?? null,
                'status' => 'active',
                'registered_by' => $actor->id,
                'registered_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
            foreach (['mother_patient_id' => 'mother', 'father_patient_id' => 'father', 'guardian_patient_id' => 'guardian'] as $field => $type) {
                if (! empty($data[$field])) {
                    $this->relate($child->id, (int) $data[$field], $type, $actor, true);
                }
            }
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'rch_child_registered', 'subject_type' => RchChild::class, 'subject_id' => $rchChild->id]);
            return $rchChild;
        });
    }

    public function relate(int $patientId, int $relatedPatientId, string $type, $actor, bool $primary = false): PatientRelationship
    {
        if ($patientId === $relatedPatientId) {
            throw ValidationException::withMessages(['related_patient_id' => 'Patient hawezi kuwa na relationship na yeye mwenyewe.']);
        }
        return PatientRelationship::query()->firstOrCreate([
            'facility_id' => currentFacility()?->id,
            'patient_id' => $patientId,
            'related_patient_id' => $relatedPatientId,
            'relationship_type' => $type,
        ], ['is_primary' => $primary, 'start_date' => today(), 'created_by' => $actor->id]);
    }
}
