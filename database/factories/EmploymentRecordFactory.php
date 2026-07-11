<?php

namespace Database\Factories;

use App\Models\EmploymentRecord;
use App\Models\Facility;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmploymentRecord> */
class EmploymentRecordFactory extends Factory
{
    protected $model = EmploymentRecord::class;

    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'facility_id' => Facility::factory(),
            'employment_status' => 'active',
            'employment_start_date' => now()->subMonths(2),
        ];
    }
}
