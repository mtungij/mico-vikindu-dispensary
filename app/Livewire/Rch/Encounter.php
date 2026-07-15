<?php

namespace App\Livewire\Rch;

use App\Livewire\Forms\Rch\RchEncounterForm;
use App\Models\RchEncounter;
use App\Models\Visit;
use App\Services\RchWorkflowService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Encounter extends Component
{
    public Visit $visit;
    public ?RchEncounter $encounter = null;
    public RchEncounterForm $form;

    public function mount(Visit $visit, RchWorkflowService $workflow): void
    {
        Gate::authorize('rch.start-encounter');
        abort_unless($visit->facility_id === currentFacility()?->id, 404);
        $this->visit = $visit;
        $this->encounter = RchEncounter::query()->where('visit_id', $visit->id)->latest()->first() ?? $workflow->startEncounter($visit, 'rch_general', auth()->user());
        $this->form->fillFromModel($this->encounter);
    }

    public function save(): void
    {
        $this->encounter->update(array_merge($this->form->normalize(), ['updated_by' => auth()->id()]));
        Notifier::success('RCH encounter saved.');
    }

    public function complete(RchWorkflowService $workflow): mixed
    {
        Gate::authorize('rch.complete-encounter');
        $workflow->completeEncounter($this->encounter, auth()->user());
        Notifier::success('RCH encounter completed.');
        return redirect()->route('rch.index');
    }

    public function render(): View
    {
        return view('livewire.rch.encounter')->layout('components.layouts.app', ['title' => 'RCH Encounter', 'description' => $this->visit->patient->fullName()]);
    }
}
