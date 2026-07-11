<?php

namespace App\Livewire\Reports;

use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Reception extends Component
{
    public function mount(): void { abort_unless(auth()->user()->can('reports.view') || auth()->user()->can('patients.export'), 403); }
    public function render(): View
    {
        $visits = Visit::query()->forCurrentFacility()->with('destinationDepartment')->get();
        return view('livewire.reports.reception', ['total' => $visits->count(), 'new' => $visits->where('visit_type.value','new_patient')->count(), 'returning' => $visits->where('visit_type.value','returning_patient')->count(), 'departmentCounts' => $visits->groupBy(fn($v) => $v->destinationDepartment?->name ?? 'None')->map->count()])
            ->layout('components.layouts.app', ['title' => 'Reception Report', 'description' => 'Visits na destinations.']);
    }
}
