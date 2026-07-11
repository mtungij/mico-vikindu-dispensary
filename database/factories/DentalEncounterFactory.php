<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\DentalEncounter;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalEncounterFactory extends Factory
{
    protected $model = DentalEncounter::class;
    public function definition(): array { $facility = Facility::query()->first() ?? Facility::factory()->create(); $patient = Patient::factory()->create(['facility_id'=>$facility->id]); $visit = Visit::factory()->create(['facility_id'=>$facility->id,'patient_id'=>$patient->id]); $user = User::factory()->create(); $clinical = ClinicalEncounter::factory()->create(['facility_id'=>$facility->id,'patient_id'=>$patient->id,'visit_id'=>$visit->id,'provider_user_id'=>$user->id]); return ['facility_id'=>$facility->id,'patient_id'=>$patient->id,'visit_id'=>$visit->id,'clinical_encounter_id'=>$clinical->id,'provider_user_id'=>$user->id,'dental_encounter_number'=>'DEN-'.fake()->unique()->numerify('2026-######'),'status'=>'in_progress','started_at'=>now(),'created_by'=>$user->id]; }
}
