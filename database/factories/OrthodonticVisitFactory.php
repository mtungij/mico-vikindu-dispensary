<?php

namespace Database\Factories;

use App\Models\OrthodonticCase;
use App\Models\OrthodonticVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrthodonticVisitFactory extends Factory { protected $model = OrthodonticVisit::class; public function definition(): array { $case = OrthodonticCase::factory()->create(); return ['orthodontic_case_id'=>$case->id,'visit_date'=>today(),'visit_type'=>'review','provider_user_id'=>$case->assigned_dentist]; } }
