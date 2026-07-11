<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffProfile> */
class StaffProfileFactory extends Factory
{
    protected $model = StaffProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'facility_id' => Facility::factory(),
            'employee_number' => 'STF-'.fake()->unique()->numerify('####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['male', 'female']),
            'nationality' => 'Tanzanian',
            'primary_phone' => '+2557'.fake()->numerify('########'),
            'personal_email' => fake()->optional()->safeEmail(),
        ];
    }
}
