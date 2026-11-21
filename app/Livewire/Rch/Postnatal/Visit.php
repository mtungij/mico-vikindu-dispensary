<?php

namespace App\Livewire\Rch\Postnatal;

use App\Enums\RchService;
use App\Livewire\Forms\Rch\PncVisitForm;
use App\Models\ActivityLog;
use App\Models\PncVisit;
use App\Models\RchEncounter;
use App\Services\RchProgrammeVisitService;
use App\Services\VitalSignAssessmentService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Visit extends Component
{
    public RchEncounter $encounter;

    public PncVisitForm $form;

    public function mount(int $linkedVisit, RchProgrammeVisitService $programmes): void
    {
        $this->encounter = $programmes->open($linkedVisit, RchService::Pnc, auth()->user());
        $record = $this->encounter->pncVisit;
        if ($record) {
            $this->form->fill($record->only([...array_keys(PncVisitForm::NOTES), 'maternal_vitals']));
        } else {
            $this->form->maternal_vitals = $this->encounter->visit->latestCompletedTriageAssessment
                ?->only(array_keys(PncVisitForm::VITALS)) ?? [];
        }
        $this->form->visit_date = $record?->visit_date->toDateString() ?? today()->toDateString();
        $this->form->delivery_date = $record?->delivery_date?->toDateString();
        $this->form->follow_up_date = $record?->follow_up_date?->toDateString();
    }

    public function save(RchProgrammeVisitService $programmes, VitalSignAssessmentService $vitals): void
    {
        $programmes->save($this->encounter, RchService::Pnc, auth()->user(), function (RchEncounter $encounter) use ($vitals): void {
            $data = $this->form->validate();
            $vitals->validateVitalRanges($data['maternal_vitals']);
            $record = $encounter->pncVisit()->firstOrNew();
            $previous = $record->only(array_keys($data));
            $record->fill([
                ...$data,
                'facility_id' => $encounter->facility_id,
                'visit_id' => $encounter->visit_id,
                'patient_id' => $encounter->patient_id,
                'provider_user_id' => auth()->id(),
            ]);
            if ($record->isDirty()) {
                $event = $record->exists ? 'pnc_visit_updated' : 'pnc_visit_created';
                $record->save();
                ActivityLog::query()->create([
                    'user_id' => auth()->id(),
                    'event' => $event,
                    'subject_type' => PncVisit::class,
                    'subject_id' => $record->id,
                    'old_values' => $previous,
                    'new_values' => $record->only(array_keys($data)),
                ]);
            }
        });

        Notifier::success(__('rch.visit_saved'));
    }

    public function render(): View
    {
        app(RchProgrammeVisitService::class)->authorizeContext($this->encounter, RchService::Pnc, auth()->user());
        $encounter = $this->encounter->refresh();

        return view('livewire.rch.postnatal.visit', [
            'record' => $encounter->pncVisit?->load('provider'),
            'editable' => ! $encounter->clinicalEncounter->isReadOnly() && $encounter->status === 'in_progress',
            'history' => PncVisit::query()->where('facility_id', $encounter->facility_id)->where('patient_id', $encounter->patient_id)
                ->with(['rchEncounter', 'provider'])->latest('visit_date')->latest('id')->limit(20)->get(),
        ])->layout('components.layouts.app', ['title' => 'PNC Visit']);
    }
}
