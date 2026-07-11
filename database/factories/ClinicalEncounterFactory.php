<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicalEncounterFactory extends Factory
{
    protected $model = ClinicalEncounter::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'patient_id' => Patient::factory(), 'visit_id' => Visit::factory(), 'department_id' => Department::factory(), 'encounter_type' => 'opd', 'encounter_number' => 'ENC-'.now()->year.'-'.$this->faker->unique()->numerify('######'), 'provider_user_id' => User::factory(), 'started_at' => now(), 'status' => 'in_progress', 'created_by' => User::factory()]; }
}
