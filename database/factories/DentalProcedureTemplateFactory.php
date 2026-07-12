<?php

namespace Database\Factories;

use App\Models\DentalProcedureTemplate;
use App\Models\DentalProcedureType;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalProcedureTemplateFactory extends Factory
{
    protected $model = DentalProcedureTemplate::class;
    public function definition(): array { return ['facility_id'=>Facility::factory(),'name'=>fake()->words(2, true),'code'=>fake()->unique()->lexify('TPL_????'),'dental_procedure_type_id'=>DentalProcedureType::factory(),'requires_consent'=>false,'send_to_observation'=>false,'is_active'=>true,'created_by'=>User::factory()]; }
}
