<?php

namespace Database\Factories;

use App\Models\DentalTreatmentPlan;
use App\Models\DentalTreatmentPlanItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalTreatmentPlanItemFactory extends Factory { protected $model = DentalTreatmentPlanItem::class; public function definition(): array { $plan = DentalTreatmentPlan::factory()->create(); return ['dental_treatment_plan_id'=>$plan->id,'service_id'=>Service::query()->where('facility_id',$plan->facility_id)->first()?->id,'description_snapshot'=>'Dental service','quantity'=>1,'unit_price_snapshot'=>0,'total_amount'=>0,'status'=>'planned']; } }
