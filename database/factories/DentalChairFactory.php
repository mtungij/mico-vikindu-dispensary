<?php

namespace Database\Factories;

use App\Models\DentalChair;
use App\Models\DentalRoom;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalChairFactory extends Factory
{
    protected $model = DentalChair::class;
    public function definition(): array { return ['facility_id'=>Facility::factory(),'dental_room_id'=>DentalRoom::factory(),'name'=>'Dental Chair '.fake()->unique()->numberBetween(1,99),'code'=>fake()->unique()->lexify('CHAIR_????'),'status'=>'available','is_active'=>true]; }
}
