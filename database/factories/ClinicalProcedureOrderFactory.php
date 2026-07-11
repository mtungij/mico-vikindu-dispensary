<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalProcedureOrder;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicalProcedureOrderFactory extends Factory
{
    protected $model = ClinicalProcedureOrder::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'patient_id' => Patient::factory(), 'visit_id' => Visit::factory(), 'clinical_encounter_id' => ClinicalEncounter::factory(), 'ordered_by' => User::factory(), 'procedure_name_snapshot' => 'Dressing', 'status' => 'ordered', 'created_by' => User::factory()]; }
}
