<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\PhysicalExamination;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhysicalExaminationFactory extends Factory
{
    protected $model = PhysicalExamination::class;
    public function definition(): array { return ['clinical_encounter_id' => ClinicalEncounter::factory(), 'examination_system' => 'general', 'findings' => 'No acute distress.', 'status' => 'normal', 'created_by' => User::factory()]; }
}
