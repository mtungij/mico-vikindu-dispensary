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
    public function definition(): array
    {
        $start = now()->addDay()->setTime(9, 0);

        return [
            'facility_id' => Facility::factory(),
            'patient_id' => Patient::factory(),
            'department_id' => Department::factory(),
            'appointment_number' => 'APT-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'appointment_type' => 'general_consultation',
            'appointment_date' => $start->toDateString(),
            'appointment_time' => $start->format('H:i:s'),
            'estimated_duration' => 30,
            'priority' => 'normal',
            'scheduled_start' => $start,
            'scheduled_end' => $start->copy()->addMinutes(30),
            'status' => 'booked',
            'created_by' => User::factory(),
        ];
    }
}
