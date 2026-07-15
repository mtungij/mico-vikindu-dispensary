<?php

namespace App\Livewire\Appointments;

use App\Models\Department;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;

class Book extends Create
{
    public function render()
    {
        return view('livewire.appointments.book', [
            'patients' => Patient::query()->forCurrentFacility()->orderBy('first_name')->limit(200)->get(),
            'departments' => Department::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
            'staff' => User::query()->whereHas('staffProfile', fn ($query) => $query->where('facility_id', currentFacility()?->id))->orderBy('name')->get(),
            'services' => Service::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('components.layouts.app', [
            'title' => $this->appointment ? 'Edit Appointment' : 'Book Appointment',
            'description' => 'Book consultation, follow-up, dental, RCH, laboratory and procedure appointments.',
        ]);
    }
}
