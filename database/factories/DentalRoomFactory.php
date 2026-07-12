<?php

namespace Database\Factories;

use App\Models\DentalRoom;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalRoomFactory extends Factory
{
    protected $model = DentalRoom::class;
    public function definition(): array { return ['facility_id'=>Facility::factory(),'name'=>'Dental Room '.fake()->unique()->numberBetween(1,99),'code'=>fake()->unique()->lexify('ROOM_????'),'location'=>'Dental wing','is_active'=>true]; }
}
