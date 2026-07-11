<?php

namespace Database\Factories;

use App\Models\Icd10Code;
use Illuminate\Database\Eloquent\Factories\Factory;

class Icd10CodeFactory extends Factory
{
    protected $model = Icd10Code::class;
    public function definition(): array { return ['code' => $this->faker->unique()->bothify('A##'), 'title' => $this->faker->sentence(3), 'is_billable' => true, 'is_active' => true]; }
}
