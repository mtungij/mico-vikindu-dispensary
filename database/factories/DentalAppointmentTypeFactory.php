<?php

namespace Database\Factories;

use App\Models\DentalAppointmentType;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalAppointmentTypeFactory extends Factory
{
    protected $model = DentalAppointmentType::class;
    public function definition(): array { return ['facility_id'=>Facility::factory(),'name'=>fake()->words(2, true),'code'=>fake()->unique()->lexify('APT_????'),'default_duration_minutes'=>30,'is_system'=>false,'is_active'=>true,'sort_order'=>0]; }
}
