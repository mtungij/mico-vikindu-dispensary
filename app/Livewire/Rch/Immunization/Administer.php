<?php

namespace App\Livewire\Rch\Immunization;

use App\Enums\RchService;
use App\Livewire\Forms\Rch\ImmunizationAdministrationForm;
use App\Models\ImmunizationAdministration;
use App\Models\ImmunizationScheduleItem;
use App\Models\MedicineBatch;
use App\Models\Patient;
use App\Models\RchChild;
use App\Models\RchEncounter;
use App\Models\Vaccine;
use App\Services\ImmunizationAdministrationService;
use App\Services\RchProgrammeVisitService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Administer extends Component
{
    public ?RchChild $child = null;

    public ?RchEncounter $linkedEncounter = null;

    public ImmunizationAdministrationForm $form;

    public function mount(?RchChild $rchChild = null, ?int $linkedVisit = null): void
    {
        Gate::authorize('rch.immunization.administer');
        if ($linkedVisit !== null) {
            $this->linkedEncounter = app(RchProgrammeVisitService::class)->open($linkedVisit, RchService::ChildMonitoring, auth()->user(), 'rch.immunization.administer');
        } else {
            abort_unless($rchChild?->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility(), 404);
            $this->child = $rchChild;
        }
        $this->form->administration_date = today()->toDateString();
    }

    public function save(ImmunizationAdministrationService $service, RchProgrammeVisitService $programmes): mixed
    {
        Gate::authorize('rch.immunization.administer');
        $data = $this->form->normalize();
        $vaccine = $this->vaccines()->findOrFail($data['vaccine_id']);
        if ($this->linkedEncounter) {
            $this->validate(['form.status' => ['required', Rule::in(['administered', 'not_given', 'refused', 'contraindicated', 'deferred'])]]);
            if ($data['status'] !== 'administered') {
                Gate::authorize('rch.immunization.mark-not-given');
            }
            $programmes->save($this->linkedEncounter, RchService::ChildMonitoring, auth()->user(), function (RchEncounter $encounter, $visit, Patient $patient) use ($service, $programmes, $data, $vaccine): void {
                if (ImmunizationAdministration::query()->where('facility_id', $encounter->facility_id)
                    ->where('rch_encounter_id', $encounter->id)->where('vaccine_id', $vaccine->id)
                    ->where('immunization_schedule_item_id', $data['immunization_schedule_item_id'])
                    ->whereDate('administration_date', $data['administration_date'])->where('status', $data['status'])->exists()) {
                    return;
                }
                if ($data['medicine_batch_id']) {
                    MedicineBatch::query()->where('facility_id', $encounter->facility_id)->findOrFail($data['medicine_batch_id']);
                }
                if ($data['immunization_schedule_item_id']) {
                    ImmunizationScheduleItem::query()->where('vaccine_id', $vaccine->id)
                        ->whereHas('schedule', fn ($query) => $query->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $encounter->facility_id)))
                        ->findOrFail($data['immunization_schedule_item_id']);
                }
                $child = $programmes->childProfile($patient, auth()->user());
                $service->administer($child, $vaccine, [...$data, 'visit_id' => $visit->id, 'rch_encounter_id' => $encounter->id], auth()->user());
            }, 'rch.immunization.administer');
            Notifier::success('Vaccine administration recorded.');

            return redirect()->route('rch.visits.child-growth', $this->linkedEncounter->visit_id);
        }

        abort_unless($this->child?->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility(), 404);
        $service->administer($this->child, $vaccine, $data, auth()->user());
        Notifier::success('Vaccine administration recorded.');

        return redirect()->route('rch.children.show', $this->child);
    }

    private function vaccines(): Builder
    {
        return Vaccine::query()->where(fn ($query) => $query->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id))->where('is_active', true);
    }

    public function render(): View
    {
        if ($this->linkedEncounter) {
            app(RchProgrammeVisitService::class)->authorizeContext($this->linkedEncounter, RchService::ChildMonitoring, auth()->user(), 'rch.immunization.administer');
        } else {
            Gate::authorize('rch.immunization.administer');
            abort_unless($this->child?->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility(), 404);
        }

        return view('livewire.rch.immunization.administer', ['vaccines' => $this->vaccines()->get()])
            ->layout('components.layouts.app', ['title' => 'Administer Vaccine']);
    }
}
