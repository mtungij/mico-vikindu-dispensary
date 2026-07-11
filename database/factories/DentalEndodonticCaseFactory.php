<?php

namespace Database\Factories;

use App\Models\DentalEncounter;
use App\Models\DentalEndodonticCase;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalEndodonticCaseFactory extends Factory { protected $model = DentalEndodonticCase::class; public function definition(): array { $e = DentalEncounter::factory()->create(); return ['facility_id'=>$e->facility_id,'patient_id'=>$e->patient_id,'tooth_number'=>'11','dental_encounter_id'=>$e->id,'diagnosis'=>'Pulpitis','status'=>'planned','provider_user_id'=>$e->provider_user_id]; } }
