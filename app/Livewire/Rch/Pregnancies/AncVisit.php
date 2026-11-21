<?php

namespace App\Livewire\Rch\Pregnancies;

use App\Enums\RchService;
use App\Livewire\Forms\Rch\AncVisitForm;
use App\Models\AncVisit as AncVisitModel;
use App\Models\Patient;
use App\Models\Pregnancy;
use App\Models\RchEncounter;
use App\Services\PregnancyDatingService;
use App\Services\PregnancyRiskAssessmentService;
use App\Services\PregnancyService;
use App\Services\RchProgrammeVisitService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class AncVisit extends Component
{
    public ?Pregnancy $pregnancy = null;

    public ?RchEncounter $linkedEncounter = null;

    public ?AncVisitModel $record = null;

    public ?int $pregnancyId = null;

    public ?string $lmpDate = null;

    public AncVisitForm $form;

    public function mount(?Pregnancy $pregnancy = null, ?int $linkedVisit = null): void
    {
        Gate::authorize('rch.anc.record-visit');
        if ($linkedVisit !== null) {
            $this->linkedEncounter = app(RchProgrammeVisitService::class)->open($linkedVisit, RchService::Anc, auth()->user());
            $this->record = $this->linkedRecord($this->linkedEncounter);
            if ($this->record) {
                $this->pregnancy = $this->record->pregnancy;
                $this->form->fillFromModel($this->record);
                $this->form->visit_date = $this->record->visit_date->toDateString();
                $this->form->next_visit_date = $this->record->next_visit_date?->toDateString();

                return;
            }
            $this->form->fill($this->linkedEncounter->visit->latestCompletedTriageAssessment?->only(['weight_kg', 'systolic_bp', 'diastolic_bp']) ?? []);
        } else {
            abort_unless($pregnancy?->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility(), 404);
            $this->pregnancy = $pregnancy;
        }
        $this->form->visit_date = today()->toDateString();
    }

    public function save(PregnancyDatingService $dating, PregnancyRiskAssessmentService $risk): mixed
    {
        Gate::authorize('rch.anc.record-visit');
        if ($this->linkedEncounter) {
            $visit = app(RchProgrammeVisitService::class)->save($this->linkedEncounter, RchService::Anc, auth()->user(), function (RchEncounter $encounter, $visit, Patient $patient) use ($dating, $risk): AncVisitModel {
                if ($record = $this->linkedRecord($encounter)) {
                    return $record;
                }
                $data = $this->form->normalize();
                $this->validate(['lmpDate' => ['nullable', 'date', 'before_or_equal:today']]);
                $pregnancies = $this->activePregnancies($patient->id)->lockForUpdate()->get();
                $pregnancy = $this->pregnancyId ? $pregnancies->find($this->pregnancyId) : ($pregnancies->count() === 1 ? $pregnancies->first() : null);
                if (! $pregnancy && ($this->pregnancyId || $pregnancies->isNotEmpty())) {
                    throw ValidationException::withMessages(['pregnancyId' => 'Select the pregnancy for this visit.']);
                }
                $pregnancy ??= app(PregnancyService::class)->register($patient, ['lmp_date' => $this->lmpDate], auth()->user());

                return $this->recordVisit($pregnancy, [...$data, 'visit_id' => $visit->id, 'rch_encounter_id' => $encounter->id], $dating, $risk);
            });
            $this->record = $visit;
            Notifier::success(__('rch.visit_saved'));

            return redirect()->route('rch.visits.anc', $this->linkedEncounter->visit_id);
        }

        abort_unless($this->pregnancy?->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility(), 404);
        $this->recordVisit($this->pregnancy, $this->form->normalize(), $dating, $risk);
        Notifier::success('ANC visit recorded.');

        return redirect()->route('rch.pregnancies.show', $this->pregnancy);
    }

    private function recordVisit(Pregnancy $pregnancy, array $data, PregnancyDatingService $dating, PregnancyRiskAssessmentService $risk): AncVisitModel
    {
        return DB::transaction(function () use ($pregnancy, $data, $dating, $risk): AncVisitModel {
            $pregnancy = Pregnancy::query()->forCurrentFacility()->lockForUpdate()->findOrFail($pregnancy->id);
            $ga = $pregnancy->lmp_date ? $dating->calculateGestationalAge($pregnancy->lmp_date, $data['visit_date']) : ['weeks' => 0, 'days' => 0];
            $visit = $pregnancy->ancVisits()->create([
                ...$data, 'facility_id' => $pregnancy->facility_id, 'patient_id' => $pregnancy->patient_id,
                'anc_visit_number' => (int) $pregnancy->ancVisits()->withTrashed()->max('anc_visit_number') + 1,
                'gestational_age_weeks' => $ga['weeks'], 'gestational_age_days' => $ga['days'], 'reviewed_by' => auth()->id(),
            ]);
            $risk->assessPregnancy($pregnancy, $visit, $data, auth()->user());

            return $visit;
        });
    }

    private function linkedRecord(RchEncounter $encounter): ?AncVisitModel
    {
        return AncVisitModel::query()->forCurrentFacility()->where('rch_encounter_id', $encounter->id)
            ->where('patient_id', $encounter->patient_id)->latest('id')->first();
    }

    private function activePregnancies(int $patientId): Builder
    {
        return Pregnancy::query()->forCurrentFacility()->where('patient_id', $patientId)->where('status', 'active')->orderBy('id');
    }

    public function render(): View
    {
        if ($this->linkedEncounter) {
            app(RchProgrammeVisitService::class)->authorizeContext($this->linkedEncounter, RchService::Anc, auth()->user());
        } else {
            Gate::authorize('rch.anc.record-visit');
            abort_unless($this->pregnancy?->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility(), 404);
        }

        return view('livewire.rch.pregnancies.anc-visit', [
            'pregnancies' => $this->linkedEncounter ? $this->activePregnancies($this->linkedEncounter->patient_id)->get() : collect(),
        ])->layout('components.layouts.app', ['title' => 'Record ANC Visit']);
    }
}
