<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = Patient::class;
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'patient_number' => 'PAT-'.fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['male', 'female']),
            'date_of_birth' => now()->subYears(fake()->numberBetween(1, 80))->toDateString(),
            'patient_status' => 'active',
            'registered_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
