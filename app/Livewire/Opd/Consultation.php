<?php

namespace App\Livewire\Opd;

use App\Enums\ClinicalOutcome;
use App\Enums\VisitStatus;
use App\Livewire\Forms\AppointmentForm;
use App\Livewire\Forms\ClinicalComplaintForm;
use App\Livewire\Forms\ClinicalEncounterForm;
use App\Livewire\Forms\DiagnosisForm;
use App\Livewire\Forms\LaboratoryOrderForm;
use App\Livewire\Forms\PhysicalExaminationForm;
use App\Livewire\Forms\PrescriptionItemForm;
use App\Livewire\Forms\ProcedureOrderForm;
use App\Livewire\Forms\ReferralForm;
use App\Models\ClinicalEncounter;
use App\Models\LaboratoryTest;
use App\Models\Medicine;
use App\Models\PatientQueue;
use App\Models\Service;
use App\Models\Visit;
use App\Services\ClinicalEncounterService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class Consultation extends Component
{
    public Visit $visit;
    public ClinicalEncounter $encounter;
    public ClinicalEncounterForm $form;
    public ClinicalComplaintForm $complaintForm;
    public PhysicalExaminationForm $examForm;
    public DiagnosisForm $diagnosisForm;
    public LaboratoryOrderForm $labForm;
    public PrescriptionItemForm $prescriptionItemForm;
    public ProcedureOrderForm $procedureForm;
    public AppointmentForm $appointmentForm;
    public ReferralForm $referralForm;
    public string $activeTab = 'summary';
    public string $saveState = '';

    public function mount(Visit $visit, ClinicalEncounterService $service): void
    {
        Gate::authorize('opd.consult');
        abort_unless($visit->facility_id === currentFacility()?->id, 403);
        abort_unless(auth()->user()?->belongsToCurrentFacility(), 403);
        abort_unless($this->visitCanOpenOpdConsultation($visit), 403);

        $this->visit = $visit->load([
            'patient.primaryPayerProfile.insuranceProvider',
            'patient.primaryPayerProfile.corporateAccount',
            'patient.diagnoses',
            'latestTriageAssessment',
            'invoice.insuranceProvider',
            'invoice.corporateAccount',
            'currentDepartment',
            'currentAssignedUser',
            'currentQueue',
        ]);
        $this->encounter = $this->visit->activeClinicalEncounter ?: $service->startEncounter($this->visit, auth()->user());
        Gate::authorize('view', $this->encounter);
        $this->form->fillFromModel($this->encounter);
        $this->appointmentForm->patient_id = $this->visit->patient_id;
        $this->appointmentForm->department_id = $this->encounter->department_id;
    }

    private function visitCanOpenOpdConsultation(Visit $visit): bool
    {
        $visit->loadMissing(['currentDepartment', 'activeClinicalEncounter']);

        if ($visit->currentDepartment?->code !== 'OPD') {
            return false;
        }

        if (! in_array($visit->visit_status, [VisitStatus::InProgress, VisitStatus::InQueue, VisitStatus::InConsultation, VisitStatus::AwaitingDepartment], true)) {
            return false;
        }

        if ($visit->activeClinicalEncounter?->department_id === $visit->current_department_id) {
            return true;
        }

        return PatientQueue::query()
            ->where('visit_id', $visit->id)
            ->where('department_id', $visit->current_department_id)
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->exists();
    }

    public function autosave(ClinicalEncounterService $service): void
    {
        Gate::authorize('update', $this->encounter);
        $this->saveState = 'Inahifadhi...';
        $this->validateOnly('form.chief_complaint');
        $this->encounter = $service->saveDraft($this->encounter, $this->form->normalize(), auth()->user());
        $this->saveState = 'Imehifadhiwa';
    }

    public function saveDraft(ClinicalEncounterService $service): void
    {
        Gate::authorize('update', $this->encounter);
        $this->validate();
        $this->encounter = $service->saveDraft($this->encounter, $this->form->normalize(), auth()->user());
        Notifier::success('Draft imehifadhiwa.');
    }

    public function addComplaint(ClinicalEncounterService $service): void
    {
        $this->complaintForm->validate();
        $service->addComplaint($this->encounter, $this->complaintForm->normalize(), auth()->user());
        $this->complaintForm->resetForm();
        Notifier::success('Complaint imeongezwa.');
    }

    public function saveExamination(ClinicalEncounterService $service): void
    {
        $this->examForm->validate();
        $service->updateExamination($this->encounter, $this->examForm->normalize(), auth()->user());
        $this->examForm->resetForm();
        Notifier::success('Examination imehifadhiwa.');
    }

    public function addDiagnosis(ClinicalEncounterService $service): void
    {
        Gate::authorize('diagnoses.create');
        $this->diagnosisForm->validate();
        $service->addDiagnosis($this->encounter, $this->diagnosisForm->normalize(), auth()->user());
        $this->diagnosisForm->resetForm();
        Notifier::success('Diagnosis imeongezwa.');
    }

    #[On('icd10-selected')]
    public function selectIcd10(string $code, string $title): void
    {
        $this->diagnosisForm->icd10_code = $code;
        $this->diagnosisForm->diagnosis_name = $title;
    }

    public function addLabOrder(ClinicalEncounterService $service): void
    {
        Gate::authorize('laboratory-orders.create');
        $this->labForm->validate();
        $service->addLabOrder($this->encounter, $this->labForm->normalize(), auth()->user());
        $this->labForm->resetForm();
        Notifier::success('Lab order imeundwa.');
    }

    public function addPrescription(ClinicalEncounterService $service): void
    {
        Gate::authorize('prescriptions.create');
        $this->prescriptionItemForm->validate();
        if ($this->prescriptionItemForm->medicine_id) {
            $medicine = Medicine::query()
                ->where('facility_id', $this->encounter->facility_id)
                ->where('is_active', true)
                ->findOrFail($this->prescriptionItemForm->medicine_id);

            $this->prescriptionItemForm->medication_name = $medicine->name;
            $this->prescriptionItemForm->generic_name = $medicine->generic?->name;
            $this->prescriptionItemForm->strength = $medicine->strength;
            $this->prescriptionItemForm->dosage_form = $medicine->dosageForm?->name;
            $this->prescriptionItemForm->route = $medicine->route?->name;
        }
        $service->addPrescription($this->encounter, ['items' => [$this->prescriptionItemForm->normalize()]], auth()->user());
        $this->prescriptionItemForm->resetForm();
        Notifier::success('Prescription imeundwa.');
    }

    public function addProcedure(ClinicalEncounterService $service): void
    {
        Gate::authorize('procedure-orders.create');
        $this->procedureForm->validate();
        $service->addProcedureOrder($this->encounter, $this->procedureForm->normalize(), auth()->user());
        $this->procedureForm->resetForm();
        Notifier::success('Procedure order imeundwa.');
    }

    public function createFollowUp(ClinicalEncounterService $service): void
    {
        Gate::authorize('appointments.create');
        $this->appointmentForm->validate();
        $service->createFollowUp($this->encounter, $this->appointmentForm->normalize(), auth()->user());
        Notifier::success('Follow-up appointment imeundwa.');
    }

    public function createReferral(ClinicalEncounterService $service): void
    {
        Gate::authorize('referrals.create');
        $this->referralForm->validate();
        $service->createReferral($this->encounter, $this->referralForm->normalize(), auth()->user());
        Notifier::success('Referral imeandaliwa.');
    }

    public function signOff(ClinicalEncounterService $service): void
    {
        $this->encounter = $service->signOff($this->encounter, auth()->user());
        Notifier::success('Encounter imesainiwa.');
    }

    public function complete(ClinicalEncounterService $service): mixed
    {
        Gate::authorize('complete', $this->encounter);
        $this->encounter = $service->completeEncounter($this->encounter, auth()->user());
        Notifier::success('Consultation imekamilishwa.');
        return redirect()->route('opd.index');
    }

    public function render(): View
    {
        $this->visit->loadMissing([
            'patient.primaryPayerProfile.insuranceProvider',
            'patient.primaryPayerProfile.corporateAccount',
            'patient.diagnoses',
            'latestTriageAssessment',
            'invoice.insuranceProvider',
            'invoice.corporateAccount',
            'currentDepartment',
            'currentAssignedUser',
            'currentQueue',
        ]);
        $this->encounter->load(['provider', 'complaints', 'examinations', 'diagnoses', 'laboratoryOrders.items.results', 'prescriptions.items.medicine', 'procedureOrders', 'appointments', 'referrals', 'amendments']);

        return view('livewire.opd.consultation', [
            'labTests' => LaboratoryTest::query()->forCurrentFacility()->with(['service', 'category', 'specimenType'])->where('is_active', true)->whereHas('service', fn ($query) => $query->where('is_active', true))->orderBy('name')->get(),
            'labServices' => Service::query()->forCurrentFacility()->where('service_type', 'laboratory_test')->where('is_active', true)->get(),
            'procedureServices' => Service::query()->forCurrentFacility()->where('service_type', 'procedure')->where('is_active', true)->get(),
            'medicines' => Medicine::query()->forCurrentFacility()->with(['generic', 'dosageForm', 'route'])->where('is_active', true)->orderBy('name')->get(),
            'outcomes' => ClinicalOutcome::cases(),
        ])->layout('components.layouts.app', ['title' => 'OPD Consultation', 'description' => $this->visit->patient->fullName().' - '.$this->visit->visit_number]);
    }
}
