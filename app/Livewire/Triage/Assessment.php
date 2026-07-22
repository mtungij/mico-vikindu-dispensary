<?php

namespace App\Livewire\Triage;

use App\Enums\TriageLevel;
use App\Enums\TriageStatus;
use App\Livewire\Forms\TriageAssessmentForm;
use App\Models\TriageAssessment;
use App\Models\Visit;
use App\Services\TriageService;
use App\Services\VitalSignAssessmentService;
use App\Support\Notifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

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
        if ($this->assessment?->status === TriageStatus::Completed) {
            Notifier::warning('Triage iliyokamilika haiwezi kuhifadhiwa kama draft.');

            return;
        }

        $this->form->validateDraft();
        $this->assessment = $service->saveAssessment($this->assessment, $this->form->normalize(), auth()->user());
        Notifier::success('Draft ya triage imehifadhiwa.');
    }

    public function complete(TriageService $service): mixed
    {
        try {
            Gate::authorize('triage.complete');
        } catch (AuthorizationException) {
            Notifier::error('Huna ruhusa ya kukamilisha Triage hii.');

            return null;
        }

        if ($this->assessment?->status === TriageStatus::Completed) {
            $this->addError('completion', 'Triage hii tayari imekamilishwa.');
            Notifier::warning('Triage hii tayari imekamilishwa.');

            return null;
        }

        try {
            $this->form->validateCompletion();
        } catch (ValidationException $exception) {
            $firstError = array_key_first($exception->errors()) ?? 'form.triage_level';
            $this->dispatch('triage-validation-failed', field: str($firstError)->after('form.')->toString());

            throw $exception;
        }

        try {
            $this->assessment = $service->completeAssessment($this->assessment, $this->form->normalize(), auth()->user());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Triage hii haiwezi kukamilishwa kwa sasa.';
            $this->addError('completion', $message);
            $this->dispatch('triage-validation-failed', field: 'triage_level');
            Notifier::warning($message);

            return null;
        } catch (Throwable $exception) {
            report($exception);
            Notifier::error('Imeshindikana kukamilisha Triage. Tafadhali jaribu tena au wasiliana na msimamizi.');

            return null;
        }

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
