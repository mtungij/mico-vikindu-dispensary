<?php

namespace Database\Factories;

use App\Models\ClinicalAlert;
use App\Models\Facility;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicalAlertFactory extends Factory
{
    protected $model = ClinicalAlert::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'patient_id' => Patient::factory(), 'alert_type' => 'abnormal_vital', 'severity' => 'warning', 'title' => 'Alert', 'message' => 'Clinical alert', 'status' => 'active']; }
}
