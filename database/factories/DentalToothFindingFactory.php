<?php

namespace Database\Factories;

use App\Models\DentalFindingType;
use App\Models\DentalToothFinding;
use App\Models\DentalToothRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalToothFindingFactory extends Factory { protected $model = DentalToothFinding::class; public function definition(): array { $r = DentalToothRecord::factory()->create(); $t = DentalFindingType::factory()->create(['facility_id'=>$r->facility_id]); return ['facility_id'=>$r->facility_id,'dental_tooth_record_id'=>$r->id,'dental_encounter_id'=>$r->dental_encounter_id,'finding_type_id'=>$t->id,'surface'=>'mesial','finding_status'=>'active','diagnosed_by'=>$r->created_by,'diagnosed_at'=>now(),'created_by'=>$r->created_by]; } }
