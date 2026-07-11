<?php

namespace Database\Factories;

use App\Models\DentalConsent;
use App\Models\DentalEncounter;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalConsentFactory extends Factory { protected $model = DentalConsent::class; public function definition(): array { $e = DentalEncounter::factory()->create(); return ['facility_id'=>$e->facility_id,'patient_id'=>$e->patient_id,'visit_id'=>$e->visit_id,'dental_encounter_id'=>$e->id,'consent_type'=>'general_dental_treatment','consent_text_snapshot'=>'Consent text','patient_or_guardian_name'=>'Patient','consent_given'=>true,'signed_at'=>now(),'clinician_user_id'=>$e->provider_user_id]; } }
