<?php

namespace Database\Factories;

use App\Enums\DepartmentType;
use App\Models\Department;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Department> */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'department_type' => fake()->randomElement(DepartmentType::cases())->value,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
