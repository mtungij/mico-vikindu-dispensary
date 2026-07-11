<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\SpecimenType;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpecimenTypeFactory extends Factory
{
    protected $model = SpecimenType::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'name' => fake()->unique()->word(), 'code' => fake()->unique()->lexify('SP???'), 'container_type' => 'Tube', 'is_active' => true]; }
}
