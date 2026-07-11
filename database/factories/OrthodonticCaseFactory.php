<?php

namespace Database\Factories;

use App\Models\DentalEncounter;
use App\Models\OrthodonticCase;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrthodonticCaseFactory extends Factory { protected $model = OrthodonticCase::class; public function definition(): array { $e = DentalEncounter::factory()->create(); return ['facility_id'=>$e->facility_id,'patient_id'=>$e->patient_id,'dental_encounter_id'=>$e->id,'case_number'=>'ORT-'.fake()->unique()->numerify('2026-######'),'chief_concern'=>'Braces','status'=>'assessment','assigned_dentist'=>$e->provider_user_id,'created_by'=>$e->provider_user_id]; } }
