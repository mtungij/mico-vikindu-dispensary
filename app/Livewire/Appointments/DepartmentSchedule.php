<?php

namespace App\Livewire\Appointments;

use App\Models\Department;
use App\Models\DepartmentSchedule as DepartmentScheduleModel;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Masmerise\Toaster\Toaster as Notifier;

class DepartmentSchedule extends Component
{
    public ?int $department_id = null;
    public ?int $editingId = null;
    public bool $showModal = false;
    public string $working_day = 'monday';
    public ?string $opening_time = '08:00';
    public ?string $closing_time = '17:00';
    public ?string $lunch_start = '13:00';
    public ?string $lunch_end = '14:00';
    public ?int $slot_duration = 30;
    public ?int $maximum_daily_capacity = 50;
    public bool $is_active = true;

    public function mount(): void { Gate::authorize('appointments.manage-department-schedules'); }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $schedule = DepartmentScheduleModel::query()->forCurrentFacility()->findOrFail($id);
        $this->editingId = $schedule->id;
        $this->department_id = $schedule->department_id;
        $this->working_day = $schedule->working_day ?? 'monday';
        $this->opening_time = $schedule->opening_time ? substr($schedule->opening_time, 0, 5) : '08:00';
        $this->closing_time = $schedule->closing_time ? substr($schedule->closing_time, 0, 5) : '17:00';
        $this->lunch_start = $schedule->lunch_start ? substr($schedule->lunch_start, 0, 5) : '13:00';
        $this->lunch_end = $schedule->lunch_end ? substr($schedule->lunch_end, 0, 5) : '14:00';
        $this->slot_duration = $schedule->slot_duration;
        $this->maximum_daily_capacity = $schedule->maximum_daily_capacity;
        $this->is_active = (bool) $schedule->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        Gate::authorize('appointments.manage-department-schedules');
        $data = $this->validate(['department_id'=>['required','integer'],'working_day'=>['required','string'],'opening_time'=>['nullable','date_format:H:i'],'closing_time'=>['nullable','date_format:H:i'],'lunch_start'=>['nullable','date_format:H:i'],'lunch_end'=>['nullable','date_format:H:i'],'slot_duration'=>['required','integer','min:5'],'maximum_daily_capacity'=>['nullable','integer','min:1'],'is_active'=>['boolean']]);
        DepartmentScheduleModel::query()->updateOrCreate(['id'=>$this->editingId], [...$data, 'facility_id'=>currentFacility()->id, 'updated_by'=>auth()->id(), 'created_by'=>auth()->id()]);
        $this->showModal = false;
        Notifier::success('Department schedule saved.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetErrorBag();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->department_id = null;
        $this->working_day = 'monday';
        $this->opening_time = '08:00';
        $this->closing_time = '17:00';
        $this->lunch_start = '13:00';
        $this->lunch_end = '14:00';
        $this->slot_duration = 30;
        $this->maximum_daily_capacity = 50;
        $this->is_active = true;
    }

    public function render()
    {
        return view('livewire.appointments.department-schedule', ['schedules'=>DepartmentScheduleModel::query()->forCurrentFacility()->with('department')->get(), 'departments'=>Department::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get()])->layout('components.layouts.app', ['title'=>'Department Schedules','description'=>'Department opening hours and daily appointment capacity.']);
    }
}
