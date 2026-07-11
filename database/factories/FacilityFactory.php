<?php

namespace Database\Factories;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Facility> */
class FacilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Dispensary',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000000',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'ward' => 'Kijitonyama',
            'physical_address' => 'Kijitonyama',
            'setup_completed_at' => now(),
        ];
    }
}
