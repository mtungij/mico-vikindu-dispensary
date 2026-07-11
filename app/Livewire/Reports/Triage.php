<?php

namespace App\Livewire\Reports;

use App\Models\TriageAssessment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Triage extends Component
{
    public function mount(): void { Gate::authorize('reports.view'); }
    public function render(): View
    {
        return view('livewire.reports.triage', ['rows' => TriageAssessment::query()->forCurrentFacility()->with(['patient', 'assessor'])->latest('assessed_at')->limit(50)->get()])
            ->layout('components.layouts.app', ['title' => 'Triage Report', 'description' => 'Ripoti ya triage na abnormal vitals.']);
    }
}
