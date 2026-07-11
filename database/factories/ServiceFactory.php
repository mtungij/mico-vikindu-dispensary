<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;
    public function definition(): array
    {
        return ['facility_id' => Facility::factory(), 'service_category_id' => ServiceCategory::factory(), 'name' => fake()->unique()->words(3, true), 'code' => strtoupper(fake()->unique()->lexify('SRV???')), 'service_type' => 'consultation', 'requires_payment' => true, 'is_active' => true, 'created_by' => User::factory()];
    }
}
