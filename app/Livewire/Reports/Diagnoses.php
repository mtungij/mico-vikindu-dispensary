<?php

namespace App\Livewire\Reports;

use App\Models\Diagnosis;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Diagnoses extends Component
{
    public function mount(): void { Gate::authorize('reports.view'); }
    public function render(): View
    {
        return view('livewire.reports.diagnoses', ['rows' => Diagnosis::query()->forCurrentFacility()->with('patient')->latest('diagnosed_at')->limit(50)->get()])
            ->layout('components.layouts.app', ['title' => 'Diagnosis Report', 'description' => 'Ripoti ya diagnoses na ICD-10 codes.']);
    }
}
