<?php

namespace App\Livewire\Appointments;

use App\Models\Appointment;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Calendar extends Component
{
    public string $view = 'daily';
    public string $date;
    public ?int $department_id = null;
    public ?int $staff_id = null;
    public ?string $appointment_type = null;
    public ?string $status = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('appointments.view-calendar') || auth()->user()?->can('appointments.view'), 403);
        $this->date = today()->toDateString();
    }

    public function render()
    {
        $start = match ($this->view) {
            'weekly' => \Carbon\Carbon::parse($this->date)->startOfWeek(),
            'monthly' => \Carbon\Carbon::parse($this->date)->startOfMonth(),
            default => \Carbon\Carbon::parse($this->date)->startOfDay(),
        };
        $end = match ($this->view) {
            'weekly' => $start->copy()->endOfWeek(),
            'monthly' => $start->copy()->endOfMonth(),
            default => $start->copy()->endOfDay(),
        };

        return view('livewire.appointments.calendar', [
            'appointments' => Appointment::query()->forCurrentFacility()->with(['patient', 'department', 'staff'])
                ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
                ->when($this->department_id, fn ($query) => $query->where('department_id', $this->department_id))
                ->when($this->staff_id, fn ($query) => $query->where('staff_id', $this->staff_id))
                ->when($this->appointment_type, fn ($query) => $query->where('appointment_type', $this->appointment_type))
                ->when($this->status, fn ($query) => $query->where('status', $this->status))
                ->orderBy('appointment_date')->orderBy('appointment_time')->get(),
            'departments' => Department::query()->forCurrentFacility()->orderBy('name')->get(),
            'staff' => User::query()->whereHas('staffProfile', fn ($query) => $query->where('facility_id', currentFacility()?->id))->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Appointment Calendar', 'description' => 'Daily, weekly and monthly appointment view.']);
    }
}
