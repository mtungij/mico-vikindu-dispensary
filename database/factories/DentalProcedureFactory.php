<?php

namespace Database\Factories;

use App\Models\DentalEncounter;
use App\Models\DentalProcedure;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalProcedureFactory extends Factory { protected $model = DentalProcedure::class; public function definition(): array { $e = DentalEncounter::factory()->create(); return ['facility_id'=>$e->facility_id,'dental_encounter_id'=>$e->id,'patient_id'=>$e->patient_id,'visit_id'=>$e->visit_id,'service_id'=>Service::query()->where('facility_id',$e->facility_id)->first()?->id,'procedure_number'=>'DPR-'.fake()->unique()->numerify('2026-######'),'procedure_type'=>'preventive','procedure_name_snapshot'=>'Dental Check-up','performed_by'=>$e->provider_user_id,'status'=>'planned','created_by'=>$e->provider_user_id]; } }
