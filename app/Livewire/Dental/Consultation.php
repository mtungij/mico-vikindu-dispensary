<?php

namespace App\Livewire\Dental;

use App\Livewire\Forms\DentalDiagnosisForm;
use App\Livewire\Forms\DentalEncounterForm;
use App\Livewire\Forms\DentalProcedureForm;
use App\Livewire\Forms\DentalTreatmentPlanForm;
use App\Models\DentalEncounter;
use App\Models\Service;
use App\Models\Visit;
use App\Services\DentalDiagnosisService;
use App\Services\DentalEncounterService;
use App\Services\DentalProcedureService;
use App\Services\DentalTreatmentPlanService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Consultation extends Component
{
    public Visit $visit; public DentalEncounter $dentalEncounter; public DentalEncounterForm $form; public DentalDiagnosisForm $diagnosisForm; public DentalTreatmentPlanForm $planForm; public DentalProcedureForm $procedureForm; public string $activeTab = 'summary'; public bool $showDiagnosisModal=false; public bool $showPlanModal=false; public bool $showProcedureModal=false; public string $saveState='';
    public function mount(Visit $visit, DentalEncounterService $service): void { Gate::authorize('dental.consult'); abort_unless($visit->facility_id === currentFacility()?->id, 404); $this->visit=$visit->load(['patient','invoice.items','payerProfile']); $this->dentalEncounter = DentalEncounter::query()->where('visit_id',$visit->id)->whereNotIn('status',['completed','cancelled','referred'])->first() ?: $service->start($visit, auth()->user()); $this->form->fillFromModel($this->dentalEncounter); }
    public function autosave(DentalEncounterService $service): void { $this->saveState='Inahifadhi...'; $this->dentalEncounter=$service->saveDraft($this->dentalEncounter,$this->form->normalize(),auth()->user()); $this->saveState='Imehifadhiwa'; }
    public function addDiagnosis(DentalDiagnosisService $service): void { Gate::authorize('dental.create-diagnosis'); $service->add($this->dentalEncounter,$this->diagnosisForm->normalize(),auth()->user()); $this->diagnosisForm->resetForm(); $this->showDiagnosisModal=false; Notifier::success('Diagnosis imeongezwa.'); }
    public function createPlan(DentalTreatmentPlanService $service): void { Gate::authorize('dental.create-treatment-plan'); $service->createPlan($this->dentalEncounter,$this->planForm->normalize(),auth()->user()); $this->planForm->resetForm(); $this->showPlanModal=false; Notifier::success('Treatment plan imeundwa.'); }
    public function createProcedure(DentalProcedureService $service): void { $data=$this->procedureForm->normalize(); $dentalService=Service::query()->where('facility_id',currentFacility()?->id)->where('id',$data['service_id'])->firstOrFail(); $service->createProcedure($this->dentalEncounter,$dentalService,$data,auth()->user()); $this->procedureForm->resetForm(); $this->showProcedureModal=false; Notifier::success('Procedure imeanza na charge imeongezwa.'); }
    public function complete(DentalEncounterService $service): mixed { Gate::authorize('complete', $this->dentalEncounter); $service->saveDraft($this->dentalEncounter,$this->form->normalize(),auth()->user()); $service->complete($this->dentalEncounter,auth()->user()); Notifier::success('Dental encounter imekamilishwa.'); return redirect()->route('dental.index'); }
    public function render(): View
    {
        $this->dentalEncounter->load(['patient','toothRecords.findings.type','diagnoses','treatmentPlans.items','procedures.service','attachments','labOrders']);
        return view('livewire.dental.consultation', ['dentalServices'=>Service::query()->forCurrentFacility()->whereIn('service_type',['dental_service','procedure'])->where('is_active', true)->orderBy('name')->get()])
            ->layout('components.layouts.app', ['title'=>'Dental Consultation','description'=>$this->visit->patient->fullName().' - '.$this->visit->visit_number]);
    }
}
