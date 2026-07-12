<?php

namespace Database\Factories;

use App\Models\DentalConsentTemplate;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalConsentTemplateFactory extends Factory
{
    protected $model = DentalConsentTemplate::class;
    public function definition(): array { return ['facility_id'=>Facility::factory(),'name'=>fake()->words(2, true),'code'=>fake()->unique()->lexify('CON_????'),'consent_type'=>'general_treatment','content'=>'Consent content','risks'=>'Risks','alternatives'=>'Alternatives','is_active'=>true]; }
}
