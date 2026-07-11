<?php

namespace App\Livewire\Observation;

use App\Livewire\Forms\BedsideProcedureForm;
use App\Livewire\Forms\BedTransferForm;
use App\Livewire\Forms\IntakeOutputForm;
use App\Livewire\Forms\IvFluidForm;
use App\Livewire\Forms\MedicationAdministrationForm;
use App\Livewire\Forms\NursingHandoverForm;
use App\Livewire\Forms\NursingObservationForm;
use App\Livewire\Forms\ObservationClinicalReviewForm;
use App\Livewire\Forms\ObservationDischargeForm;
use App\Livewire\Forms\ObservationOrderForm;
use App\Models\Bed;
use App\Models\ObservationAdmission;
use App\Services\BedManagementService;
use App\Services\BedsideProcedureService;
use App\Services\IntakeOutputService;
use App\Services\IvFluidService;
use App\Services\MedicationAdministrationService;
use App\Services\NursingHandoverService;
use App\Services\NursingObservationService;
use App\Services\ObservationClinicalReviewService;
use App\Services\ObservationDischargeService;
use App\Services\ObservationOrderService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AdmissionShow extends Component
{
    public ObservationAdmission $observationAdmission; public string $tab='summary'; public bool $showVitals=false; public bool $showOrder=false; public bool $showMedication=false; public bool $showIv=false; public bool $showProcedure=false; public bool $showIo=false; public bool $showHandover=false; public bool $showReview=false; public bool $showDischarge=false; public bool $showTransfer=false;
    public NursingObservationForm $vitalsForm; public ObservationOrderForm $orderForm; public MedicationAdministrationForm $medicationForm; public IvFluidForm $ivForm; public BedsideProcedureForm $procedureForm; public IntakeOutputForm $ioForm; public NursingHandoverForm $handoverForm; public ObservationClinicalReviewForm $reviewForm; public ObservationDischargeForm $dischargeForm; public BedTransferForm $transferForm;
    public function mount(ObservationAdmission $observationAdmission): void { Gate::authorize('view', $observationAdmission); abort_unless($observationAdmission->facility_id === currentFacility()?->id, 404); $this->observationAdmission=$observationAdmission; }
    public function recordVitals(NursingObservationService $s): void { Gate::authorize('observation.record-nursing-observation'); $this->vitalsForm->validate(); $s->record($this->observationAdmission, $this->vitalsForm->normalize(), auth()->user()); $this->showVitals=false; $this->vitalsForm->resetForm(); Notifier::success('observation.saved'); }
    public function createOrder(ObservationOrderService $s): void { Gate::authorize('observation.create-order'); $this->orderForm->validate(); $s->create($this->observationAdmission,$this->orderForm->normalize(),auth()->user()); $this->showOrder=false; $this->orderForm->resetForm(); Notifier::success('observation.saved'); }
    public function scheduleMedication(MedicationAdministrationService $s): void { Gate::authorize('observation.administer-medication'); $this->medicationForm->validate(); $s->schedule($this->observationAdmission,$this->medicationForm->normalize(),auth()->user()); $this->showMedication=false; $this->medicationForm->resetForm(); Notifier::success('observation.saved'); }
    public function startIv(IvFluidService $s): void { Gate::authorize('observation.record-iv-fluid'); $this->ivForm->validate(); $s->start($this->observationAdmission,$this->ivForm->normalize(),auth()->user()); $this->showIv=false; $this->ivForm->resetForm(); Notifier::success('observation.saved'); }
    public function recordProcedure(BedsideProcedureService $s): void { Gate::authorize('observation.record-procedure'); $this->procedureForm->validate(); $s->record($this->observationAdmission,$this->procedureForm->normalize(),auth()->user()); $this->showProcedure=false; $this->procedureForm->resetForm(); Notifier::success('observation.saved'); }
    public function recordIo(IntakeOutputService $s): void { Gate::authorize('observation.record-intake-output'); $this->ioForm->validate(); $s->record($this->observationAdmission,$this->ioForm->normalize(),auth()->user()); $this->showIo=false; $this->ioForm->resetForm(); Notifier::success('observation.saved'); }
    public function createHandover(NursingHandoverService $s): void { Gate::authorize('observation.create-handover'); $this->handoverForm->validate(); $s->create($this->observationAdmission,$this->handoverForm->normalize(),auth()->user()); $this->showHandover=false; $this->handoverForm->resetForm(); Notifier::success('observation.saved'); }
    public function clinicalReview(ObservationClinicalReviewService $s): void { Gate::authorize('observation.clinical-review'); $this->reviewForm->validate(); $s->complete($this->observationAdmission,$this->reviewForm->normalize(),auth()->user()); $this->showReview=false; $this->reviewForm->resetForm(); Notifier::success('observation.saved'); }
    public function discharge(ObservationDischargeService $s): void { Gate::authorize('discharge', $this->observationAdmission); $this->dischargeForm->validate(); $d=$s->discharge($this->observationAdmission,$this->dischargeForm->normalize(),auth()->user()); Notifier::success('observation.discharged'); $this->redirectRoute('observation.discharges.print',$d); }
    public function transfer(BedManagementService $s): void { Gate::authorize('observation.transfer-bed'); $this->transferForm->validate(); $bed=Bed::query()->forCurrentFacility()->findOrFail($this->transferForm->destination_bed_id); $s->transferBed($this->observationAdmission,$bed,auth()->user(),$this->transferForm->reason); $this->showTransfer=false; Notifier::success('observation.bed_transferred'); }
    public function render(): View { return view('livewire.observation.admission-show', ['admission'=>$this->observationAdmission->load(['patient','visit.invoice.items','bed','room','observations.recorder','orders','medicationAdministrations','ivFluids','tasks','handovers','discharge']), 'availableBeds'=>Bed::query()->forCurrentFacility()->where('status','available')->get()])->layout('components.layouts.app', ['title'=>$this->observationAdmission->admission_number,'description'=>'Observation chart ya mgonjwa.']); }
}
