<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\LaboratorySampleRejectionReason;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratorySampleRejectionReasonFactory extends Factory
{
    protected $model = LaboratorySampleRejectionReason::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'code' => fake()->unique()->lexify('RJ???'), 'name' => fake()->words(3, true), 'requires_recollection' => true, 'is_active' => true]; }
}
