<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\LaboratoryOrder;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryOrderFactory extends Factory
{
    protected $model = LaboratoryOrder::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'patient_id' => Patient::factory(), 'visit_id' => Visit::factory(), 'clinical_encounter_id' => ClinicalEncounter::factory(), 'ordered_by' => User::factory(), 'order_number' => 'LAB-'.now()->year.'-'.$this->faker->unique()->numerify('######'), 'status' => 'ordered', 'payment_status' => 'not_required', 'ordered_at' => now(), 'created_by' => User::factory()]; }
}
