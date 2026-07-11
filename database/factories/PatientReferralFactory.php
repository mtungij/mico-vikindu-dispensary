<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Patient;
use App\Models\PatientReferral;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientReferralFactory extends Factory
{
    protected $model = PatientReferral::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'patient_id' => Patient::factory(), 'visit_id' => Visit::factory(), 'referral_number' => 'REF-'.now()->year.'-'.$this->faker->unique()->numerify('######'), 'referral_type' => 'external', 'destination_facility_name' => 'Regional Hospital', 'reason' => 'Specialist review', 'urgency' => 'routine', 'referred_by' => User::factory(), 'referred_at' => now(), 'status' => 'prepared', 'created_by' => User::factory()]; }
}
