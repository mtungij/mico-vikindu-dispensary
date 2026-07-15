<?php

namespace App\Livewire\Appointments;

use App\Livewire\Forms\AppointmentForm;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Masmerise\Toaster\Toaster as Notifier;

class Create extends Component
{
    public AppointmentForm $form;
    public ?Appointment $appointment = null;

    public function mount(?Appointment $appointment = null): void
    {
        Gate::authorize($appointment?->exists ? 'update' : 'create', $appointment?->exists ? $appointment : Appointment::class);
        $this->appointment = $appointment?->exists ? $appointment : null;
        if ($this->appointment) {
            $this->form->fillFromModel($this->appointment);
        }
    }

    public function save(AppointmentService $service): void
    {
        $this->form->validate();
        $appointment = $this->appointment
            ? $service->update($this->appointment, $this->form->normalize(), auth()->user())
            : $service->create($this->form->normalize(), auth()->user());

        Notifier::success($this->appointment ? 'Appointment updated.' : 'Appointment booked.');
        $this->redirectRoute('appointments.index');
    }

    public function render()
    {
        return view('livewire.appointments.create', [
            'patients' => Patient::query()->forCurrentFacility()->orderBy('first_name')->limit(200)->get(),
            'departments' => Department::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
            'staff' => User::query()->whereHas('staffProfile', fn ($query) => $query->where('facility_id', currentFacility()?->id))->orderBy('name')->get(),
            'services' => Service::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => $this->appointment ? 'Edit Appointment' : 'Book Appointment', 'description' => 'Book consultation, follow-up, dental, RCH, laboratory and procedure appointments.']);
    }
}
