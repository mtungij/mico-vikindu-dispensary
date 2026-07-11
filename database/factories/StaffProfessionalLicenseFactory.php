<?php

namespace Database\Factories;

use App\Models\StaffProfessionalLicense;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffProfessionalLicense> */
class StaffProfessionalLicenseFactory extends Factory
{
    protected $model = StaffProfessionalLicense::class;

    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'license_type' => 'Practicing License',
            'professional_body' => 'Medical Council of Tanganyika',
            'registration_number' => fake()->unique()->bothify('REG-####'),
            'expiry_date' => now()->addYear(),
            'status' => 'active',
            'verification_status' => 'pending',
        ];
    }
}
