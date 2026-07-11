<?php

namespace Database\Factories;

use App\Enums\LaboratoryResultType;
use App\Models\Facility;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryTestParameterFactory extends Factory
{
    protected $model = LaboratoryTestParameter::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'laboratory_test_id' => LaboratoryTest::factory(), 'name' => fake()->unique()->words(2, true), 'code' => fake()->unique()->lexify('PAR???'), 'result_type' => LaboratoryResultType::Numeric, 'unit' => 'mg/dL', 'is_active' => true]; }
}
