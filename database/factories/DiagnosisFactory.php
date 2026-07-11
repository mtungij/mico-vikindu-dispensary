<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\Diagnosis;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiagnosisFactory extends Factory
{
    protected $model = Diagnosis::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'patient_id' => Patient::factory(), 'visit_id' => Visit::factory(), 'clinical_encounter_id' => ClinicalEncounter::factory(), 'diagnosis_type' => 'final', 'diagnosis_name' => 'Fever', 'certainty' => 'confirmed', 'is_primary' => true, 'diagnosed_by' => User::factory(), 'diagnosed_at' => now(), 'status' => 'active', 'created_by' => User::factory()]; }
}
