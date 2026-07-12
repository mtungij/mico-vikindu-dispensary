<?php

namespace App\Livewire\Reports;

use App\Models\Department;
use App\Models\PatientQueue;
use App\Models\Visit;
use App\Models\VisitMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

class Workflow extends Component
{
    public ?int $departmentId = null;
    public string $from;
    public string $to;

    public function mount(): void
    {
        Gate::authorize('workflow.reports.view');
        $this->from = today()->toDateString();
        $this->to = today()->toDateString();
    }

    public function render(): View
    {
        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();
        $queues = PatientQueue::query()->forCurrentFacility()->with('department')->whereBetween('queue_date', [$from->toDateString(), $to->toDateString()])->when($this->departmentId, fn ($q) => $q->where('department_id', $this->departmentId));
        $movements = VisitMovement::query()->where('facility_id', currentFacility()?->id)->with(['fromDepartment', 'toDepartment'])->whereBetween('moved_at', [$from, $to])->when($this->departmentId, fn ($q) => $q->where(fn ($qq) => $qq->where('from_department_id', $this->departmentId)->orWhere('to_department_id', $this->departmentId)));

        return view('livewire.reports.workflow', [
            'departments' => Department::query()->forCurrentFacility()->where('queue_enabled', true)->orderBy('name')->get(),
            'summary' => [
                'queue_items' => (clone $queues)->count(),
                'avg_waiting' => round((float) (clone $queues)->whereNotNull('waiting_seconds')->avg('waiting_seconds') / 60, 1),
                'avg_service' => round((float) (clone $queues)->whereNotNull('service_seconds')->avg('service_seconds') / 60, 1),
                'transfers' => (clone $movements)->where('movement_type', 'department_transfer')->count(),
                'completed_visits' => Visit::query()->forCurrentFacility()->whereBetween('completed_at', [$from, $to])->count(),
                'cancelled_visits' => Visit::query()->forCurrentFacility()->whereBetween('cancelled_at', [$from, $to])->count(),
            ],
            'workload' => (clone $queues)->selectRaw('department_id, count(*) as total')->groupBy('department_id')->get(),
            'movements' => (clone $movements)->latest('moved_at')->limit(50)->get(),
        ])->layout('components.layouts.app', ['title' => 'Workflow Reports', 'description' => 'Queue performance and patient movement reports.']);
    }
}
