<?php

namespace App\Livewire\Appointments;

use App\Models\DoctorSchedule as DoctorScheduleModel;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Masmerise\Toaster\Toaster as Notifier;

class DoctorSchedule extends Component
{
    public ?int $staff_id = null;
    public ?int $department_id = null;
    public ?int $editingId = null;
    public bool $showModal = false;
    public array $working_days = ['mon','tue','wed','thu','fri'];
    public string $working_day = 'monday';
    public ?string $start_time = '08:00';
    public ?string $end_time = '17:00';
    public ?string $break_start = '13:00';
    public ?string $break_end = '14:00';
    public ?int $slot_duration = 30;
    public ?int $max_patients_per_day = 20;
    public ?int $max_patients_per_hour = 4;
    public ?string $unavailable_dates = null;
    public bool $is_active = true;

    public function mount(): void { Gate::authorize('appointments.manage-doctor-schedules'); }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $schedule = DoctorScheduleModel::query()->forCurrentFacility()->findOrFail($id);
        $this->editingId = $schedule->id;
        $this->staff_id = $schedule->staff_id;
        $this->department_id = $schedule->department_id;
        $this->working_day = $schedule->working_day ?? 'monday';
        $this->start_time = $schedule->start_time ? substr($schedule->start_time, 0, 5) : '08:00';
        $this->end_time = $schedule->end_time ? substr($schedule->end_time, 0, 5) : '17:00';
        $this->break_start = $schedule->break_start ? substr($schedule->break_start, 0, 5) : '13:00';
        $this->break_end = $schedule->break_end ? substr($schedule->break_end, 0, 5) : '14:00';
        $this->slot_duration = $schedule->slot_duration;
        $this->max_patients_per_day = $schedule->max_patients_per_day;
        $this->max_patients_per_hour = $schedule->max_patients_per_hour;
        $this->unavailable_dates = collect($schedule->unavailable_dates ?? [])->implode(', ');
        $this->is_active = (bool) $schedule->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        Gate::authorize('appointments.manage-doctor-schedules');
        $data = $this->validate(['staff_id'=>['required','integer'],'department_id'=>['nullable','integer'],'working_day'=>['required','string'],'working_days'=>['array'],'start_time'=>['nullable','date_format:H:i'],'end_time'=>['nullable','date_format:H:i'],'break_start'=>['nullable','date_format:H:i'],'break_end'=>['nullable','date_format:H:i'],'slot_duration'=>['required','integer','min:5'],'max_patients_per_day'=>['nullable','integer','min:1'],'max_patients_per_hour'=>['nullable','integer','min:1'],'unavailable_dates'=>['nullable','string'],'is_active'=>['boolean']]);
        DoctorScheduleModel::query()->updateOrCreate(['id'=>$this->editingId], [...$data, 'facility_id'=>currentFacility()->id, 'working_days'=>[$data['working_day']], 'unavailable_dates'=>collect(explode(',', $data['unavailable_dates'] ?? ''))->map(fn($d)=>trim($d))->filter()->values()->all(), 'updated_by'=>auth()->id(), 'created_by'=>auth()->id()]);
        $this->showModal = false;
        Notifier::success('Doctor schedule saved.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetErrorBag();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->staff_id = null;
        $this->department_id = null;
        $this->working_day = 'monday';
        $this->start_time = '08:00';
        $this->end_time = '17:00';
        $this->break_start = '13:00';
        $this->break_end = '14:00';
        $this->slot_duration = 30;
        $this->max_patients_per_day = 20;
        $this->max_patients_per_hour = 4;
        $this->unavailable_dates = null;
        $this->is_active = true;
    }

    public function render()
    {
        return view('livewire.appointments.doctor-schedule', ['schedules'=>DoctorScheduleModel::query()->forCurrentFacility()->with(['staff','department'])->get(), 'departments'=>Department::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(), 'staff'=>User::query()->whereHas('staffProfile', fn($q)=>$q->where('facility_id', currentFacility()?->id))->orderBy('name')->get()])->layout('components.layouts.app', ['title'=>'Doctor Schedules','description'=>'Working days, hours, capacity and unavailable dates.']);
    }
}
