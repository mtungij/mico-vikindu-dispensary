<?php

namespace Database\Factories;

use App\Models\StaffEmergencyContact;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffEmergencyContact> */
class StaffEmergencyContactFactory extends Factory
{
    protected $model = StaffEmergencyContact::class;

    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'full_name' => fake()->name(),
            'relationship' => 'Relative',
            'primary_phone' => '+2557'.fake()->numerify('########'),
            'is_primary' => true,
        ];
    }
}
