<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'patient_id' => Patient::factory(), 'visit_id' => Visit::factory(), 'clinical_encounter_id' => ClinicalEncounter::factory(), 'prescribed_by' => User::factory(), 'prescription_number' => 'RX-'.now()->year.'-'.$this->faker->unique()->numerify('######'), 'status' => 'prescribed', 'prescribed_at' => now(), 'created_by' => User::factory()]; }
}
