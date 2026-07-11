<?php

namespace App\Livewire\Triage;

use App\Enums\TriageLevel;
use App\Livewire\Forms\TriageAssessmentForm;
use App\Models\TriageAssessment;
use App\Models\Visit;
use App\Services\TriageService;
use App\Services\VitalSignAssessmentService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Assessment extends Component
{
    public Visit $visit;
    public ?TriageAssessment $assessment = null;
    public TriageAssessmentForm $form;
    public array $suggestedAlerts = [];
    public string $suggestedLevel = 'routine';

    public function mount(Visit $visit, TriageService $service): void
    {
        Gate::authorize('triage.record-vitals');
        abort_unless($visit->facility_id === currentFacility()?->id, 404);
        $this->visit = $visit->load(['patient', 'invoice', 'destinationDepartment', 'latestTriageAssessment']);
        $this->assessment = $this->visit->latestTriageAssessment ?: $service->startAssessment($this->visit, auth()->user());
        $this->form->fillFromModel($this->assessment);
    }

    public function updated(): void
    {
        $data = $this->form->normalize();
        $vitals = app(VitalSignAssessmentService::class);
        $this->suggestedAlerts = $vitals->buildClinicalAlerts($data);
        $this->suggestedLevel = $vitals->determineTriageLevelSuggestion($data)->value;
    }

    public function saveDraft(TriageService $service): void
    {
        Gate::authorize('triage.record-vitals');
        $this->validate();
        $this->assessment = $service->saveAssessment($this->assessment, $this->form->normalize(), auth()->user());
        Notifier::success('Draft ya triage imehifadhiwa.');
    }

    public function complete(TriageService $service): mixed
    {
        Gate::authorize('triage.complete');
        $this->validate();
        $service->completeAssessment($this->assessment, $this->form->normalize(), auth()->user());
        Notifier::success('Triage imekamilishwa na mgonjwa ametumwa department husika.');
        return redirect()->route('triage.index');
    }

    public function render(): View
    {
        return view('livewire.triage.assessment', [
            'levels' => TriageLevel::cases(),
            'dangerSigns' => ['Severe breathing difficulty', 'Unconsciousness', 'Convulsions', 'Severe bleeding', 'Severe dehydration', 'Chest pain', 'Stroke signs', 'Obstetric emergency', 'Severe allergic reaction', 'Poisoning', 'Major trauma', 'High fever with danger signs', 'Child unable to drink/feed', 'Other'],
        ])->layout('components.layouts.app', ['title' => 'Triage Assessment', 'description' => 'Vipimo muhimu, danger signs na priority ya mgonjwa.']);
    }
}
