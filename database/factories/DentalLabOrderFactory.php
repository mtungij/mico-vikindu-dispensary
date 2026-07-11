<?php

namespace Database\Factories;

use App\Models\DentalEncounter;
use App\Models\DentalLabOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalLabOrderFactory extends Factory { protected $model = DentalLabOrder::class; public function definition(): array { $e = DentalEncounter::factory()->create(); return ['facility_id'=>$e->facility_id,'patient_id'=>$e->patient_id,'visit_id'=>$e->visit_id,'dental_encounter_id'=>$e->id,'order_number'=>'DLB-'.fake()->unique()->numerify('2026-######'),'work_type'=>'crown','status'=>'draft','ordered_by'=>$e->provider_user_id,'created_by'=>$e->provider_user_id]; } }
