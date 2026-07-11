<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\LaboratoryTestCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryTestCategoryFactory extends Factory
{
    protected $model = LaboratoryTestCategory::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'name' => fake()->unique()->words(2, true), 'code' => fake()->unique()->lexify('LAB???'), 'is_active' => true]; }
}
