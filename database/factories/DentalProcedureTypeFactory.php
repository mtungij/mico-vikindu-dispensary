<?php

namespace Database\Factories;

use App\Models\DentalProcedureType;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalProcedureTypeFactory extends Factory
{
    protected $model = DentalProcedureType::class;
    public function definition(): array { return ['facility_id'=>Facility::factory(),'name'=>fake()->words(2, true),'code'=>fake()->unique()->lexify('DPT_????'),'category'=>'restorative','requires_tooth'=>true,'requires_surface'=>false,'requires_consent'=>false,'requires_payment'=>true,'updates_odontogram'=>true,'can_require_observation'=>false,'is_system'=>false,'is_active'=>true,'sort_order'=>0]; }
}
