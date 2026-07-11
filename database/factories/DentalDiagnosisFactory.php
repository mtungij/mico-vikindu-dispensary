<?php

namespace Database\Factories;

use App\Models\DentalDiagnosis;
use App\Models\DentalEncounter;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalDiagnosisFactory extends Factory { protected $model = DentalDiagnosis::class; public function definition(): array { $e = DentalEncounter::factory()->create(); return ['facility_id'=>$e->facility_id,'dental_encounter_id'=>$e->id,'patient_id'=>$e->patient_id,'visit_id'=>$e->visit_id,'diagnosis_type'=>'dental','diagnosis_name'=>'Dental caries','certainty'=>'confirmed','status'=>'active','diagnosed_by'=>$e->provider_user_id,'diagnosed_at'=>now(),'created_by'=>$e->provider_user_id]; } }
