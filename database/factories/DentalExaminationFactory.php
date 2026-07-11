<?php

namespace Database\Factories;

use App\Models\DentalEncounter;
use App\Models\DentalExamination;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalExaminationFactory extends Factory { protected $model = DentalExamination::class; public function definition(): array { $e = DentalEncounter::factory()->create(); return ['facility_id'=>$e->facility_id,'dental_encounter_id'=>$e->id,'examination_type'=>'extraoral','area'=>'face symmetry','status'=>'normal','recorded_by'=>$e->provider_user_id,'recorded_at'=>now(),'created_by'=>$e->provider_user_id]; } }
