<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceCategoryFactory extends Factory
{
    protected $model = ServiceCategory::class;
    public function definition(): array
    {
        return ['facility_id' => Facility::factory(), 'name' => fake()->unique()->words(2, true), 'code' => strtoupper(fake()->unique()->lexify('???')), 'category_type' => 'consultation', 'is_active' => true, 'created_by' => User::factory()];
    }
}
