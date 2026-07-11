<?php

namespace Database\Factories;

use App\Models\DentalEncounter;
use App\Models\DentalTreatmentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalTreatmentPlanFactory extends Factory { protected $model = DentalTreatmentPlan::class; public function definition(): array { $e = DentalEncounter::factory()->create(); return ['facility_id'=>$e->facility_id,'dental_encounter_id'=>$e->id,'patient_id'=>$e->patient_id,'visit_id'=>$e->visit_id,'plan_number'=>'DTP-'.fake()->unique()->numerify('2026-######'),'title'=>'Dental treatment plan','status'=>'draft','created_by'=>$e->provider_user_id]; } }
