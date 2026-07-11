<?php

namespace Database\Factories;

use App\Models\DentalEncounter;
use App\Models\DentalToothRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalToothRecordFactory extends Factory { protected $model = DentalToothRecord::class; public function definition(): array { $e = DentalEncounter::factory()->create(); return ['facility_id'=>$e->facility_id,'dental_encounter_id'=>$e->id,'patient_id'=>$e->patient_id,'tooth_number'=>'11','dentition_type'=>'permanent','tooth_status'=>'present','created_by'=>$e->created_by]; } }
