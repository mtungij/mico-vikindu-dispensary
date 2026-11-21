<?php

namespace App\Livewire\Rch\FamilyPlanning;

use App\Enums\RchService;
use App\Livewire\Forms\Rch\FamilyPlanningVisitForm;
use App\Models\FamilyPlanningClient;
use App\Models\FamilyPlanningMethod;
use App\Models\FamilyPlanningVisit;
use App\Models\Patient;
use App\Models\RchEncounter;
use App\Services\FamilyPlanningService;
use App\Services\RchProgrammeVisitService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Visit extends Component
{
    public ?FamilyPlanningClient $client = null;

    public ?RchEncounter $linkedEncounter = null;

    public ?FamilyPlanningVisit $record = null;

    public ?int $clientId = null;

    public FamilyPlanningVisitForm $form;

    public function mount(?FamilyPlanningClient $familyPlanningClient = null, ?int $linkedVisit = null): void
    {
        Gate::authorize('rch.family-planning.record-visit');
        if ($linkedVisit !== null) {
            $this->linkedEncounter = app(RchProgrammeVisitService::class)->open($linkedVisit, RchService::FamilyPlanning, auth()->user());
            $this->record = $this->linkedRecord($this->linkedEncounter);
            if ($this->record) {
                $this->client = $this->record->client;
                $this->form->fillFromModel($this->record);
                foreach (['visit_date', 'method_start_date', 'expected_end_date', 'next_visit_date'] as $field) {
                    $this->form->{$field} = $this->record->{$field}?->toDateString();
                }

                return;
            }
            if ($this->clients()->doesntExist()) {
                $this->form->visit_type = 'initiation';
            }
        } else {
            abort_unless($familyPlanningClient?->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility(), 404);
            $this->client = $familyPlanningClient;
        }
        $this->form->visit_date = today()->toDateString();
    }

    public function save(FamilyPlanningService $service): mixed
    {
        Gate::authorize('rch.family-planning.record-visit');
        if ($this->linkedEncounter) {
            app(RchProgrammeVisitService::class)->save($this->linkedEncounter, RchService::FamilyPlanning, auth()->user(), function (RchEncounter $encounter, $visit, Patient $patient) use ($service): void {
                if ($this->linkedRecord($encounter)) {
                    return;
                }

                $data = $this->visitData();
                $clients = $this->clients()->lockForUpdate()->get();
                $client = $this->clientId ? $clients->find($this->clientId) : ($clients->count() === 1 ? $clients->first() : null);
                if (! $client && ($this->clientId || $clients->isNotEmpty())) {
                    throw ValidationException::withMessages(['clientId' => 'Select the family planning profile for this visit.']);
                }
                $client ??= $service->register($patient, [], auth()->user());
                $service->recordVisit($client, [...$data, 'visit_id' => $visit->id, 'rch_encounter_id' => $encounter->id], auth()->user());
            });
            Notifier::success(__('rch.visit_saved'));

            return redirect()->route('rch.visits.family-planning', $this->linkedEncounter->visit_id);
        }

        abort_unless($this->client?->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility(), 404);
        $service->recordVisit($this->client, $this->visitData(), auth()->user());
        Notifier::success('FP visit recorded.');

        return redirect()->route('rch.family-planning.show', $this->client);
    }

    private function linkedRecord(RchEncounter $encounter): ?FamilyPlanningVisit
    {
        return FamilyPlanningVisit::query()->forCurrentFacility()->where('rch_encounter_id', $encounter->id)
            ->where('patient_id', $encounter->patient_id)->latest('id')->first();
    }

    private function clients(): Builder
    {
        return FamilyPlanningClient::query()->forCurrentFacility()->where('patient_id', $this->linkedEncounter->patient_id)
            ->where('status', 'active')->orderBy('id');
    }

    private function visitData(): array
    {
        $data = $this->form->normalize();
        if (! empty($data['selected_method_id']) && ! $this->methods()->whereKey($data['selected_method_id'])->exists()) {
            throw ValidationException::withMessages(['form.selected_method_id' => 'Select an available family planning method.']);
        }

        return $data;
    }

    private function methods(): Builder
    {
        return FamilyPlanningMethod::query()->where(fn ($query) => $query->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id))->where('is_active', true);
    }

    public function render(): View
    {
        if ($this->linkedEncounter) {
            app(RchProgrammeVisitService::class)->authorizeContext($this->linkedEncounter, RchService::FamilyPlanning, auth()->user());
        } else {
            Gate::authorize('rch.family-planning.record-visit');
            abort_unless($this->client?->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility(), 404);
        }

        return view('livewire.rch.family-planning.visit', [
            'methods' => $this->methods()->get(),
            'clients' => $this->linkedEncounter ? $this->clients()->get() : collect(),
        ])->layout('components.layouts.app', ['title' => 'Record FP Visit']);
    }
}
