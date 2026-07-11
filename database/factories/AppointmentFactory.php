<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'patient_id' => Patient::factory(), 'department_id' => Department::factory(), 'appointment_type' => 'opd_follow_up', 'scheduled_start' => now()->addDay(), 'scheduled_end' => now()->addDay()->addMinutes(30), 'status' => 'scheduled', 'created_by' => User::factory()]; }
}
