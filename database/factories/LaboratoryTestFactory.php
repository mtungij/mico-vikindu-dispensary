<?php

namespace Database\Factories;

use App\Enums\LaboratoryResultType;
use App\Models\Facility;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestCategory;
use App\Models\Service;
use App\Models\SpecimenType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryTestFactory extends Factory
{
    protected $model = LaboratoryTest::class;
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'service_id' => Service::factory(),
            'laboratory_test_category_id' => LaboratoryTestCategory::factory(),
            'specimen_type_id' => SpecimenType::factory(),
            'name' => fake()->unique()->words(3, true),
            'code' => fake()->unique()->lexify('TST???'),
            'result_type' => LaboratoryResultType::Numeric,
            'unit' => 'mg/dL',
            'turnaround_time_minutes' => 60,
            'is_active' => true,
        ];
    }
}
