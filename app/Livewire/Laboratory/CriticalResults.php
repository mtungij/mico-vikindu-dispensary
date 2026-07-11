<?php

namespace App\Livewire\Laboratory;

use App\Models\ClinicalAlert;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class CriticalResults extends Component
{
    public function mount(): void { Gate::authorize('laboratory-critical-results.view'); }
    public function render(): View { return view('livewire.laboratory.critical-results', ['alerts' => ClinicalAlert::query()->forCurrentFacility()->with('patient')->where('alert_type', 'laboratory_critical_result')->latest()->paginate(15)])->layout('components.layouts.app', ['title' => 'Critical Results', 'description' => 'Matokeo hatari na clinical alerts.']); }
}
