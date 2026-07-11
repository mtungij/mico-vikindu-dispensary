<?php

namespace Database\Factories;

use App\Models\ClinicalComplaint;
use App\Models\ClinicalEncounter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicalComplaintFactory extends Factory
{
    protected $model = ClinicalComplaint::class;
    public function definition(): array { return ['clinical_encounter_id' => ClinicalEncounter::factory(), 'complaint' => $this->faker->sentence(3), 'severity' => 'mild', 'is_primary' => true, 'created_by' => User::factory()]; }
}
