<?php

namespace Database\Factories;

use App\Models\DentalAnaestheticType;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalAnaestheticTypeFactory extends Factory
{
    protected $model = DentalAnaestheticType::class;
    public function definition(): array { return ['facility_id'=>Facility::factory(),'name'=>'Lidocaine '.fake()->unique()->numberBetween(1,99),'code'=>fake()->unique()->lexify('ANA_????'),'generic_name'=>'Lidocaine','concentration'=>'2%','route'=>'injection','is_active'=>true]; }
}
