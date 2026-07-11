<?php

namespace App\Livewire\Clinical;

use App\Models\Patient;
use App\Services\ClinicalTimelineService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class PatientTimeline extends Component
{
    public Patient $patient;
    public string $eventType = '';

    public function mount(Patient $patient): void
    {
        Gate::authorize('opd.view-clinical-history');
        abort_unless($patient->facility_id === currentFacility()?->id, 404);
        $this->patient = $patient;
    }

    public function render(ClinicalTimelineService $timeline): View
    {
        $events = $timeline->forPatient($this->patient)->when($this->eventType, fn ($items) => $items->where('type', $this->eventType)->values());
        return view('livewire.clinical.patient-timeline', ['events' => $events]);
    }
}
