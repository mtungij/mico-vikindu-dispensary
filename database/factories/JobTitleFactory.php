<?php

namespace Database\Factories;

use App\Enums\EmploymentCategory;
use App\Models\Facility;
use App\Models\JobTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobTitle> */
class JobTitleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'name' => fake()->unique()->jobTitle(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'employment_category' => fake()->randomElement(EmploymentCategory::cases())->value,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
