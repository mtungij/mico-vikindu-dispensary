<?php

namespace App\Livewire\Appointments;

use App\Models\Appointment;
use App\Models\Department;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
use Masmerise\Toaster\Toaster as Notifier;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public ?string $date = null;
    public ?string $status = null;
    public ?string $type = null;
    public ?int $department_id = null;
    public ?int $staff_id = null;
    public ?string $cancellation_reason = null;

    public function mount(): void { Gate::authorize('appointments.view'); }

    public function checkIn(int $id, AppointmentService $service): void
    {
        $appointment = Appointment::query()->forCurrentFacility()->findOrFail($id);
        Gate::authorize('appointments.check-in');
        $service->checkIn($appointment, auth()->user());
        Notifier::success('Appointment checked in.');
    }

    public function confirm(int $id, AppointmentService $service): void
    {
        $appointment = Appointment::query()->forCurrentFacility()->findOrFail($id);
        Gate::authorize('appointments.update');
        $service->confirm($appointment, auth()->user());
        Notifier::success('Appointment confirmed.');
    }

    public function rescheduleNextDay(int $id, AppointmentService $service): void
    {
        $appointment = Appointment::query()->forCurrentFacility()->findOrFail($id);
        Gate::authorize('reschedule', $appointment);
        $start = ($appointment->scheduled_start ?: Carbon::parse($appointment->appointment_date.' '.$appointment->appointment_time))->addDay();
        $service->reschedule($appointment, [
            'appointment_date' => $start->toDateString(),
            'appointment_time' => $start->format('H:i'),
            'estimated_duration' => $appointment->estimated_duration ?: 30,
            'staff_id' => $appointment->staff_id,
        ], auth()->user());
        Notifier::success('Appointment rescheduled.');
    }

    public function cancel(int $id, AppointmentService $service): void
    {
        $appointment = Appointment::query()->forCurrentFacility()->findOrFail($id);
        Gate::authorize('cancel', $appointment);
        $service->cancel($appointment, $this->cancellation_reason ?: 'Cancelled from appointment list', auth()->user());
        $this->cancellation_reason = null;
        Notifier::success('Appointment cancelled.');
    }

    public function noShow(int $id, AppointmentService $service): void
    {
        $appointment = Appointment::query()->forCurrentFacility()->findOrFail($id);
        Gate::authorize('appointments.edit');
        $service->markNoShow($appointment, auth()->user());
        Notifier::success('Appointment marked no-show.');
    }

    public function render()
    {
        $appointments = Appointment::query()
            ->forCurrentFacility()
            ->with(['patient', 'department', 'staff', 'service'])
            ->when($this->search, function ($query): void {
                $search = '%'.$this->search.'%';
                $query->where(function ($q) use ($search): void {
                    $q->where('appointment_number', 'like', $search)
                        ->orWhereHas('patient', fn ($p) => $p->where('first_name', 'like', $search)->orWhere('last_name', 'like', $search)->orWhere('primary_phone', 'like', $search))
                        ->orWhereHas('department', fn ($d) => $d->where('name', 'like', $search))
                        ->orWhereHas('staff', fn ($s) => $s->where('name', 'like', $search))
                        ->orWhereHas('service', fn ($s) => $s->where('name', 'like', $search));
                });
            })
            ->when($this->date, fn ($query) => $query->whereDate('appointment_date', $this->date))
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->type, fn ($query) => $query->where('appointment_type', $this->type))
            ->when($this->department_id, fn ($query) => $query->where('department_id', $this->department_id))
            ->when($this->staff_id, fn ($query) => $query->where('staff_id', $this->staff_id))
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->paginate(12);

        return view('livewire.appointments.index', [
            'appointments' => $appointments,
            'departments' => Department::query()->forCurrentFacility()->orderBy('name')->get(),
            'staff' => User::query()->whereHas('staffProfile', fn ($query) => $query->where('facility_id', currentFacility()?->id))->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Appointments', 'description' => 'Search, check in, cancel and manage appointments.']);
    }
}
