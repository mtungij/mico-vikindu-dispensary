<?php

namespace App\Livewire\Appointments;

use App\Models\Appointment;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('appointments.view-dashboard') || auth()->user()?->can('appointments.view'), 403);
    }

    public function render()
    {
        $today = today();
        $query = Appointment::query()->forCurrentFacility();

        return view('livewire.appointments.dashboard', [
            'cards' => [
                'Today\'s Appointments' => (clone $query)->whereDate('appointment_date', $today)->count(),
                'Upcoming' => (clone $query)->whereDate('appointment_date', '>', $today)->count(),
                'Checked In' => (clone $query)->where('status', 'checked_in')->count(),
                'Waiting' => (clone $query)->where('status', 'waiting')->count(),
                'Completed' => (clone $query)->where('status', 'completed')->count(),
                'Cancelled' => (clone $query)->where('status', 'cancelled')->count(),
                'No Show' => (clone $query)->where('status', 'no_show')->count(),
                'Follow-up' => (clone $query)->whereIn('appointment_type', ['follow_up_visit', 'opd_follow_up'])->count(),
                'Dental Today' => (clone $query)->whereDate('appointment_date', $today)->whereIn('appointment_type', ['dental', 'dental_review'])->count(),
                'ANC Today' => (clone $query)->whereDate('appointment_date', $today)->where('appointment_type', 'anc')->count(),
                'Laboratory Today' => (clone $query)->whereDate('appointment_date', $today)->whereIn('appointment_type', ['laboratory', 'lab_review'])->count(),
            ],
            'appointments' => Appointment::query()->forCurrentFacility()->with(['patient', 'department', 'staff'])->whereDate('appointment_date', $today)->orderBy('appointment_time')->limit(10)->get(),
            'departmentSummary' => Appointment::query()->forCurrentFacility()->with('department')->whereDate('appointment_date', $today)->get()->groupBy('department.name')->map->count(),
            'providerSummary' => Appointment::query()->forCurrentFacility()->with('staff')->whereDate('appointment_date', $today)->get()->groupBy(fn ($appointment) => $appointment->staff?->name ?? 'Unassigned')->map->count(),
        ])->layout('components.layouts.app', ['title' => 'Appointment Dashboard', 'description' => 'Today, follow-up and department appointment activity.']);
    }
}
