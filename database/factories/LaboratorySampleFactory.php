<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\LaboratoryOrder;
use App\Models\LaboratorySample;
use App\Models\Patient;
use App\Models\SpecimenType;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratorySampleFactory extends Factory
{
    protected $model = LaboratorySample::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'laboratory_order_id' => LaboratoryOrder::factory(), 'patient_id' => Patient::factory(), 'visit_id' => Visit::factory(), 'sample_number' => 'SMP-'.fake()->unique()->numerify('######'), 'specimen_type_id' => SpecimenType::factory(), 'collected_by' => User::factory(), 'collected_at' => now(), 'sample_status' => 'collected', 'created_by' => User::factory()]; }
}
