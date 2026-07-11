<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryReferenceRangeFactory extends Factory
{
    protected $model = LaboratoryReferenceRange::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'laboratory_test_id' => LaboratoryTest::factory(), 'laboratory_test_parameter_id' => LaboratoryTestParameter::factory(), 'lower_limit' => 4, 'upper_limit' => 10, 'is_active' => true]; }
}
