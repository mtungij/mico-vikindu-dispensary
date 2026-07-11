<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Patient;
use App\Models\TriageAssessment;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class TriageAssessmentFactory extends Factory
{
    protected $model = TriageAssessment::class;
    public function definition(): array { $facility = Facility::factory(); return ['facility_id' => $facility, 'patient_id' => Patient::factory(), 'visit_id' => Visit::factory(), 'assessed_by' => User::factory(), 'assessed_at' => now(), 'sequence_number' => 1, 'triage_level' => 'routine', 'status' => 'completed', 'created_by' => User::factory()]; }
}
