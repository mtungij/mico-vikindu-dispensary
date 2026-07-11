<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitFactory extends Factory
{
    protected $model = Visit::class;
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'patient_id' => Patient::factory(),
            'visit_number' => 'VIS-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => Department::factory(),
            'current_department_id' => Department::factory(),
            'visit_status' => 'awaiting_triage',
            'priority' => 'normal',
            'registered_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
