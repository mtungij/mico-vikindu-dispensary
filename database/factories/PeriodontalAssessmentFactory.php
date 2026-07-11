<?php

namespace Database\Factories;

use App\Models\DentalEncounter;
use App\Models\PeriodontalAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PeriodontalAssessmentFactory extends Factory { protected $model = PeriodontalAssessment::class; public function definition(): array { $e = DentalEncounter::factory()->create(); return ['facility_id'=>$e->facility_id,'dental_encounter_id'=>$e->id,'patient_id'=>$e->patient_id,'assessment_date'=>today(),'recorded_by'=>$e->provider_user_id]; } }
