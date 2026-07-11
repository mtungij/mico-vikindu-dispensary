<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\StaffProfile;
use App\Models\StaffSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffSignatureFactory extends Factory
{
    protected $model = StaffSignature::class;

    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'staff_id' => StaffProfile::factory(),
            'signature_path' => 'staff/signatures/sample.png',
            'uploaded_by' => User::factory(),
            'uploaded_at' => now(),
            'is_active' => true,
        ];
    }
}
