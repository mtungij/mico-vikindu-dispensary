<?php

namespace App\Livewire\Reports;

use App\Models\ClinicalEncounter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Opd extends Component
{
    public function mount(): void { Gate::authorize('reports.view'); }
    public function render(): View
    {
        return view('livewire.reports.opd', ['rows' => ClinicalEncounter::query()->forCurrentFacility()->with(['patient', 'provider'])->latest()->limit(50)->get()])
            ->layout('components.layouts.app', ['title' => 'OPD Report', 'description' => 'Ripoti ya consultation na outcomes.']);
    }
}
