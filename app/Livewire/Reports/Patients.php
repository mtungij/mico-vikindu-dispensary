<?php

namespace App\Livewire\Reports;

use App\Models\Patient;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Patients extends Component
{
    public function mount(): void { abort_unless(auth()->user()->can('patients.export') || auth()->user()->can('reports.view'), 403); }
    public function render(): View
    {
        $patients = Patient::query()->forCurrentFacility()->with('primaryPayerProfile')->get();
        return view('livewire.reports.patients', ['total' => $patients->count(), 'cash' => $patients->where('primaryPayerProfile.payer_type.value', 'cash')->count(), 'insurance' => $patients->where('primaryPayerProfile.payer_type.value', 'insurance')->count(), 'genderCounts' => $patients->groupBy(fn($p) => $p->gender?->value ?? 'unknown')->map->count()])
            ->layout('components.layouts.app', ['title' => 'Patient Report', 'description' => 'Patient summary na CSV export.']);
    }
}
