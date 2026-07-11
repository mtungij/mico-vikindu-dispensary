<?php

namespace Database\Factories;

use App\Models\StaffEducationRecord;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffEducationRecord> */
class StaffEducationRecordFactory extends Factory
{
    protected $model = StaffEducationRecord::class;

    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'education_level' => 'diploma',
            'course_name' => fake()->jobTitle(),
            'institution_name' => fake()->company(),
            'graduation_year' => now()->year - 2,
            'verification_status' => 'pending',
        ];
    }
}
