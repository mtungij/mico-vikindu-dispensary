<?php

namespace Database\Factories;

use App\Enums\LaboratoryResultType;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryResultValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryResultValueFactory extends Factory
{
    protected $model = LaboratoryResultValue::class;
    public function definition(): array { return ['laboratory_result_id' => LaboratoryResult::factory(), 'parameter_name_snapshot' => fake()->words(2, true), 'result_type' => LaboratoryResultType::Numeric, 'numeric_value' => fake()->randomFloat(2, 1, 20), 'created_by' => User::factory()]; }
}
