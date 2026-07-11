<?php

namespace Database\Factories;

use App\Models\PeriodontalAssessment;
use App\Models\PeriodontalMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;

class PeriodontalMeasurementFactory extends Factory { protected $model = PeriodontalMeasurement::class; public function definition(): array { return ['periodontal_assessment_id'=>PeriodontalAssessment::factory(),'tooth_number'=>'11','site'=>'buccal','pocket_depth_mm'=>3,'bleeding_on_probing'=>false]; } }
