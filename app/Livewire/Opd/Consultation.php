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
use App\Models\ClinicalProcedureOrder;
use App\Models\Department;
use App\Models\LaboratoryTest;
use App\Models\Medicine;
use App\Models\PatientQueue;
use App\Models\PrescriptionItem;
use App\Models\Service;
use App\Models\Visit;
use App\Services\ClinicalEncounterService;
use App\Services\MedicineBillingReadinessService;
use App\Services\PrescriptionService;
use App\Services\ProcedureOrderService;
use App\Support\MedicationDirections;
use App\Support\Notifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

    public bool $icd10Selected = false;

    public ?int $editingPrescriptionItemId = null;

    public ?int $editingProcedureOrderId = null;

    public string $medicineSearch = '';

    public function mount(Visit $visit, ClinicalEncounterService $service): void
    {
        Gate::authorize('opd.consult');
        abort_unless($visit->facility_id === currentFacility()?->id, 403);
        abort_unless(auth()->user()?->belongsToCurrentFacility(), 403);
        $latestEncounter = $visit->clinicalEncounters()->with('department')->latest('id')->first();
        abort_unless($this->visitCanOpenOpdConsultation($visit, $latestEncounter), 403);

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
        $this->encounter = $this->visit->activeClinicalEncounter
            ?: ($visit->visit_status !== VisitStatus::AwaitingDoctorReview && $latestEncounter?->isTerminal()
                ? $latestEncounter
                : $service->startEncounter($this->visit, auth()->user()));
        Gate::authorize('view', $this->encounter);
        $this->form->fillFromModel($this->encounter);
        $this->appointmentForm->patient_id = $this->visit->patient_id;
        $this->appointmentForm->department_id = $this->encounter->department_id;
    }

    private function visitCanOpenOpdConsultation(Visit $visit, ?ClinicalEncounter $latestEncounter = null): bool
    {
        $visit->loadMissing(['currentDepartment', 'activeClinicalEncounter']);

        $visitIsTerminal = in_array($visit->visit_status, [VisitStatus::Completed, VisitStatus::Cancelled, VisitStatus::Referred, VisitStatus::Discharged], true);
        if ($latestEncounter?->department?->code === 'OPD' && ($latestEncounter->isTerminal() || $visitIsTerminal)) {
            return true;
        }

        if ($visit->currentDepartment?->code !== 'OPD') {
            return false;
        }

        $openStatuses = [
            VisitStatus::InProgress,
            VisitStatus::InQueue,
            VisitStatus::InConsultation,
            VisitStatus::AwaitingDepartment,
            VisitStatus::AwaitingDoctorReview,
        ];
        $laboratoryInterruptionStatuses = [
            VisitStatus::AwaitingPayment,
            VisitStatus::AwaitingLab,
            VisitStatus::AwaitingSample,
            VisitStatus::Processing,
            VisitStatus::AwaitingVerification,
            VisitStatus::ResultsReady,
            VisitStatus::AwaitingResults,
        ];
        if (! in_array($visit->visit_status, $openStatuses, true)
            && (! $visit->activeClinicalEncounter
                || ! in_array($visit->visit_status, $laboratoryInterruptionStatuses, true))) {
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
        $this->encounter = $this->encounter->refresh();
        if ($this->encounter->isReadOnly()) {
            throw ValidationException::withMessages(['encounter' => 'Consultation hii tayari imekamilika na haiwezi kuhaririwa.']);
        }
        $this->saveState = 'Inahifadhi...';
        $this->validateOnly('form.chief_complaint');
        $this->encounter = $service->saveDraft($this->encounter, $this->form->normalize(), auth()->user());
        $this->saveState = 'Imehifadhiwa';
    }

    public function saveDraft(ClinicalEncounterService $service): void
    {
        $this->resetErrorBag();

        try {
            $this->encounter = $this->encounter->refresh();
            if ($this->encounter->isReadOnly()) {
                throw ValidationException::withMessages(['encounter' => 'Consultation hii tayari imekamilika na haiwezi kuhaririwa.']);
            }
            $this->form->validate();
            $this->encounter = $service->saveDraft($this->encounter, $this->form->normalize(), auth()->user());
            Notifier::success('Draft saved successfully.');
        } catch (ValidationException $exception) {
            $this->showValidationFailure($exception);
        } catch (AuthorizationException) {
            $this->showAuthorizationFailure('You are not authorized to save this consultation draft.');
        }
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
        $this->icd10Selected = false;
        Notifier::success('Diagnosis imeongezwa.');
    }

    #[On('icd10-selected')]
    public function selectIcd10(string $code, string $title): void
    {
        $this->diagnosisForm->icd10_code = $code;
        $this->diagnosisForm->diagnosis_name = $title;
        $this->icd10Selected = true;
    }

    public function updatedDiagnosisFormIcd10Code(): void
    {
        $this->icd10Selected = false;
    }

    public function updatedDiagnosisFormDiagnosisName(): void
    {
        $this->icd10Selected = false;
    }

    public function updatedFormOutcome(?string $outcome): void
    {
        if ($outcome === ClinicalOutcome::FollowUp->value) {
            $this->form->follow_up_required = true;
        }
    }

    public function addLabOrder(ClinicalEncounterService $service): void
    {
        $this->labForm->validate();

        try {
            $service->addLabOrder($this->encounter, $this->labForm->normalize(), auth()->user());
        } catch (AuthorizationException) {
            $message = 'You are not authorized to create laboratory orders.';
            $this->addError('labForm.service_ids', $message);

            return;
        }

        $this->labForm->resetForm();
        Notifier::success('Lab order imeundwa.');
    }

    public function addPrescription(ClinicalEncounterService $service): void
    {
        Gate::authorize('prescriptions.create');
        $this->synchronizeMedicationForm();
        $this->prescriptionItemForm->validate();
        try {
            $service->addPrescription($this->encounter, ['items' => [$this->prescriptionItemForm->normalize()]], auth()->user());
        } catch (ValidationException $exception) {
            $this->showMedicineValidationFailure($exception);

            return;
        }
        $this->prescriptionItemForm->resetForm();
        Notifier::success('Prescription imeundwa.');
    }

    public function editPrescriptionItem(int $prescriptionItemId, PrescriptionService $service): void
    {
        $item = PrescriptionItem::query()->whereHas('prescription', fn ($query) => $query->where('clinical_encounter_id', $this->encounter->id)->where('facility_id', currentFacility()?->id))->findOrFail($prescriptionItemId);
        $service->assertItemEditable($item, auth()->user());
        $this->prescriptionItemForm->fillFromModel($item);
        $this->editingPrescriptionItemId = $item->id;
        $this->activeTab = 'orders';
    }

    public function updatePrescriptionItem(PrescriptionService $service): void
    {
        $this->synchronizeMedicationForm();
        $this->prescriptionItemForm->validate();
        $item = PrescriptionItem::query()->whereHas('prescription', fn ($query) => $query->where('clinical_encounter_id', $this->encounter->id)->where('facility_id', currentFacility()?->id))->findOrFail($this->editingPrescriptionItemId);
        try {
            $service->updateItem($item, $this->prescriptionItemForm->normalize(), auth()->user());
        } catch (ValidationException $exception) {
            $this->showMedicineValidationFailure($exception);

            return;
        }
        $this->cancelPrescriptionEdit();
        Notifier::success('Dawa imesasishwa.');
    }

    public function removePrescriptionItem(int $prescriptionItemId, PrescriptionService $service): void
    {
        $item = PrescriptionItem::query()->whereHas('prescription', fn ($query) => $query->where('clinical_encounter_id', $this->encounter->id)->where('facility_id', currentFacility()?->id))->findOrFail($prescriptionItemId);
        $service->removeItem($item, auth()->user());
        if ($this->editingPrescriptionItemId === $prescriptionItemId) {
            $this->cancelPrescriptionEdit();
        }
        Notifier::success('Dawa imeondolewa.');
    }

    public function cancelPrescriptionEdit(): void
    {
        $this->editingPrescriptionItemId = null;
        $this->prescriptionItemForm->resetForm();
    }

    public function updatedPrescriptionItemFormMedicineId(?int $medicineId): void
    {
        $medicine = $medicineId
            ? Medicine::query()->forCurrentFacility()->with(['dosageForm', 'dispensingUnit', 'route'])->find($medicineId)
            : null;
        $this->prescriptionItemForm->dosage_form = $medicine?->dosageForm?->name;
        $route = MedicationDirections::normalizeRoute($medicine?->route?->name);
        if ($this->editingPrescriptionItemId === null) {
            $this->prescriptionItemForm->dose = '';
            $this->prescriptionItemForm->dose_choice = '';
            $this->prescriptionItemForm->custom_dose = '';
            $this->prescriptionItemForm->route_choice = $route ?? '';
            $this->prescriptionItemForm->route = $route;
            $this->prescriptionItemForm->custom_route = '';
            $this->resetCalculatedQuantity();
        } elseif (blank($this->prescriptionItemForm->route) && $route) {
            $this->prescriptionItemForm->route_choice = $route;
            $this->prescriptionItemForm->route = $route;
        }
    }

    public function updatedPrescriptionItemFormDoseChoice(string $choice): void
    {
        $this->prescriptionItemForm->dose = $choice === 'custom' ? trim($this->prescriptionItemForm->custom_dose) : $choice;
        $this->recalculateMedicationQuantity();
    }

    public function updatedPrescriptionItemFormCustomDose(string $dose): void
    {
        if ($this->prescriptionItemForm->dose_choice === 'custom') {
            $this->prescriptionItemForm->dose = trim($dose);
            $this->recalculateMedicationQuantity();
        }
    }

    public function updatedPrescriptionItemFormFrequencyChoice(string $choice): void
    {
        $this->prescriptionItemForm->frequency = $choice === 'custom' ? trim($this->prescriptionItemForm->custom_frequency) : $choice;
        $this->recalculateMedicationQuantity();
    }

    public function updatedPrescriptionItemFormCustomFrequency(string $frequency): void
    {
        if ($this->prescriptionItemForm->frequency_choice === 'custom') {
            $this->prescriptionItemForm->frequency = trim($frequency);
            $this->recalculateMedicationQuantity();
        }
    }

    public function updatedPrescriptionItemFormRouteChoice(string $choice): void
    {
        $this->prescriptionItemForm->route = $choice === 'custom' ? trim($this->prescriptionItemForm->custom_route) : $choice;
    }

    public function updatedPrescriptionItemFormCustomRoute(string $route): void
    {
        if ($this->prescriptionItemForm->route_choice === 'custom') {
            $this->prescriptionItemForm->route = trim($route);
        }
    }

    public function updatedPrescriptionItemFormDose(): void
    {
        $this->recalculateMedicationQuantity();
    }

    public function updatedPrescriptionItemFormFrequency(): void
    {
        $this->recalculateMedicationQuantity();
    }

    public function updatedPrescriptionItemFormDurationValue(): void
    {
        $this->recalculateMedicationQuantity();
    }

    public function updatedPrescriptionItemFormDurationUnit(): void
    {
        $this->recalculateMedicationQuantity();
    }

    public function updatedPrescriptionItemFormQuantity($quantity): void
    {
        $calculated = MedicationDirections::calculateQuantity(
            $this->prescriptionItemForm->dose,
            $this->prescriptionItemForm->frequency,
            $this->prescriptionItemForm->duration_value,
            $this->prescriptionItemForm->duration_unit,
        );
        $this->prescriptionItemForm->quantity_manually_adjusted = filled($quantity)
            && ($calculated === null || abs((float) $quantity - $calculated) > 0.005);
    }

    private function synchronizeMedicationForm(): void
    {
        if (! $this->prescriptionItemForm->quantity_manually_adjusted) {
            $this->recalculateMedicationQuantity();
        }
    }

    private function recalculateMedicationQuantity(): void
    {
        $quantity = MedicationDirections::calculateQuantity(
            $this->prescriptionItemForm->dose,
            $this->prescriptionItemForm->frequency,
            $this->prescriptionItemForm->duration_value,
            $this->prescriptionItemForm->duration_unit,
        );
        $this->prescriptionItemForm->quantity_manually_adjusted = false;
        if ($quantity === null) {
            $this->prescriptionItemForm->quantity = null;
            $this->prescriptionItemForm->calculation_summary = null;

            return;
        }
        $this->prescriptionItemForm->quantity = rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
        $this->prescriptionItemForm->calculation_summary = sprintf(
            '%s × %s × %s %s = %s',
            $this->prescriptionItemForm->dose,
            MedicationDirections::displayFrequency($this->prescriptionItemForm->frequency),
            $this->prescriptionItemForm->duration_value,
            str($this->prescriptionItemForm->duration_unit)->replace('_', ' ')->toString(),
            $this->prescriptionItemForm->quantity,
        );
    }

    private function resetCalculatedQuantity(): void
    {
        $this->prescriptionItemForm->quantity = null;
        $this->prescriptionItemForm->quantity_manually_adjusted = false;
        $this->prescriptionItemForm->calculation_summary = null;
    }

    public function addProcedure(ClinicalEncounterService $service): void
    {
        Gate::authorize('procedure-orders.create');
        $this->procedureForm->validate();
        $service->addProcedureOrder($this->encounter, $this->procedureForm->normalize(), auth()->user());
        $this->procedureForm->resetForm();
        Notifier::success('Procedure order imeundwa.');
    }

    public function editProcedureOrder(int $procedureOrderId, ProcedureOrderService $service): void
    {
        $order = $this->encounterProcedureOrder($procedureOrderId);
        $service->assertOrderEditable($order, auth()->user());
        $this->procedureForm->fillFromModel($order);
        $this->editingProcedureOrderId = $order->id;
        $this->activeTab = 'orders';
    }

    public function updateProcedureOrder(ProcedureOrderService $service): void
    {
        $this->procedureForm->validate();
        $order = $this->encounterProcedureOrder((int) $this->editingProcedureOrderId);
        $service->updateOrder($order, $this->procedureForm->normalize(), auth()->user());
        $this->cancelProcedureEdit();
        Notifier::success('Procedure imesasishwa.');
    }

    public function removeProcedureOrder(int $procedureOrderId, ProcedureOrderService $service): void
    {
        $service->removeOrder($this->encounterProcedureOrder($procedureOrderId), auth()->user());
        if ($this->editingProcedureOrderId === $procedureOrderId) {
            $this->cancelProcedureEdit();
        }
        Notifier::success('Procedure imeondolewa.');
    }

    public function cancelProcedureEdit(): void
    {
        $this->editingProcedureOrderId = null;
        $this->procedureForm->resetForm();
    }

    private function encounterProcedureOrder(int $procedureOrderId): ClinicalProcedureOrder
    {
        return ClinicalProcedureOrder::query()
            ->where('clinical_encounter_id', $this->encounter->id)
            ->where('facility_id', currentFacility()?->id)
            ->findOrFail($procedureOrderId);
    }

    public function createFollowUp(ClinicalEncounterService $service): void
    {
        Gate::authorize('appointments.create');
        $this->appointmentForm->validate();
        $service->createFollowUp($this->encounter, $this->appointmentForm->normalize(), auth()->user());
        $this->form->follow_up_required = true;
        $this->form->follow_up_date = Carbon::parse($this->appointmentForm->scheduled_start)->toDateString();
        Notifier::success('Follow-up appointment imeundwa.');
    }

    public function createReferral(ClinicalEncounterService $service): void
    {
        Gate::authorize('referrals.create');
        $this->referralForm->validate();
        $service->createReferral($this->encounter, $this->referralForm->normalize(), auth()->user());
        $this->form->outcome = ClinicalOutcome::Referred->value;
        $this->encounter->refresh();
        Notifier::success('Referral imeandaliwa.');
    }

    public function signOff(ClinicalEncounterService $service): void
    {
        $this->resetErrorBag();

        try {
            Gate::authorize('signOff', $this->encounter);
            $this->form->validate();
            $this->encounter = $service->signOff($this->encounter, auth()->user(), $this->form->normalize());
            $this->form->fillFromModel($this->encounter);
            Notifier::success('Consultation signed off successfully.');
        } catch (ValidationException $exception) {
            $this->showValidationFailure($exception);
        } catch (AuthorizationException) {
            $this->showAuthorizationFailure('Only an authorized doctor or clinician can sign off this consultation.');
        }
    }

    public function completeConsultation(ClinicalEncounterService $service): mixed
    {
        $this->resetErrorBag();
        $this->encounter = $this->encounter->refresh();
        if ($this->encounter->isReadOnly()) {
            $this->form->fillFromModel($this->encounter);

            return null;
        }

        try {
            Gate::authorize('complete', $this->encounter);
            $this->form->validate();
            $this->encounter = $service->completeEncounter(
                $this->encounter,
                auth()->user(),
                [
                    ...$this->form->normalize(),
                    'follow_up_scheduled_start' => $this->appointmentForm->scheduled_start,
                    'follow_up_reason' => $this->appointmentForm->reason,
                    'follow_up_department_id' => $this->appointmentForm->department_id,
                ],
            );
            $this->encounter = $this->encounter->refresh();
            $this->encounter->load(['completer', 'signer']);
            $this->form->fillFromModel($this->encounter);
            $this->resetErrorBag();
        } catch (ValidationException $exception) {
            $this->encounter = $this->encounter->refresh();
            $this->showValidationFailure($exception);

            return null;
        } catch (AuthorizationException) {
            $this->showAuthorizationFailure('You are not authorized to complete this consultation.');

            return null;
        }

        $destinations = $service->completionDestinations($this->encounter);
        $hasAwaitingMedicinePayment = $this->encounter->prescriptions()->where('status', 'awaiting_payment')->exists();
        $message = match ($this->encounter->outcome) {
            ClinicalOutcome::AdmittedBedRest => 'Consultation completed. Patient forwarded for admission.',
            ClinicalOutcome::Observation => 'Consultation completed. Patient forwarded to Observation.',
            ClinicalOutcome::Referred => 'Consultation completed. Referral recorded successfully.',
            ClinicalOutcome::FollowUp => 'Consultation completed. Follow-up scheduled successfully.',
            default => $hasAwaitingMedicinePayment
                ? 'Consultation completed. Medicine charges were sent to Billing.'
                : ($destinations === []
                ? 'Consultation completed. Visit completed.'
                : 'Consultation completed. Patient forwarded to '.collect($destinations)->join(', ', ' and').'.'),
        };
        Notifier::success($message);

        return redirect()->route('opd.index');
    }

    public function isReadOnly(): bool
    {
        $this->encounter->setRelation('visit', $this->visit);

        return $this->encounter->isReadOnly();
    }

    public function printSummary(): mixed
    {
        $this->resetErrorBag();

        try {
            Gate::authorize('print', $this->encounter);
        } catch (AuthorizationException) {
            $this->showAuthorizationFailure('You are not authorized to print this consultation summary.');

            return null;
        }

        Notifier::success('Printable consultation summary prepared.');

        return redirect()->route('clinical-encounters.print', $this->encounter);
    }

    private function showValidationFailure(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $this->addError($field, $message);
            }
        }

        Notifier::error('Please correct the highlighted consultation errors and try again.');
    }

    private function showMedicineValidationFailure(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $field = $field === 'medicine_id' ? 'prescriptionItemForm.medicine_id' : $field;
            foreach ($messages as $message) {
                $this->addError($field, $message);
            }
        }

        Notifier::error('Medicine order was not saved. Correct the highlighted issue and try again.');
    }

    private function showAuthorizationFailure(string $message): void
    {
        $this->addError('authorization', $message);
        Notifier::error($message);
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
        $canViewLaboratoryResults = auth()->user()->can('laboratory-results.view');
        $relations = [
            'provider',
            'completer',
            'signer',
            'complaints',
            'examinations',
            'diagnoses',
            'laboratoryOrders' => fn ($query) => $query->where('facility_id', currentFacility()?->id),
            'laboratoryOrders.items',
            'prescriptions.items.medicine.dispensingUnit',
            'procedureOrders.invoiceItem',
            'appointments',
            'referrals',
            'amendments',
        ];
        if ($canViewLaboratoryResults) {
            $relations['laboratoryOrders.items.results'] = fn ($query) => $query
                ->where('facility_id', currentFacility()?->id)
                ->with(['values', 'verifier', 'releaser'])
                ->orderByDesc('result_version');
        }
        $this->encounter->load($relations);

        if (! $canViewLaboratoryResults) {
            $this->encounter->laboratoryOrders->each(
                fn ($order) => $order->items->each(fn ($item) => $item->setRelation('results', new EloquentCollection)),
            );
        }

        $medicines = Medicine::query()->forCurrentFacility()
            ->with(['generic', 'dosageForm', 'dispensingUnit', 'route', 'service'])
            ->when(strlen($this->medicineSearch) >= 2, fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$this->medicineSearch.'%')->orWhere('brand_name', 'like', '%'.$this->medicineSearch.'%')->orWhereHas('generic', fn ($g) => $g->where('name', 'like', '%'.$this->medicineSearch.'%'))))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(50)
            ->get();
        $readiness = app(MedicineBillingReadinessService::class);
        $medicines->each(fn (Medicine $medicine) => $medicine->setAttribute('billing_readiness', $readiness->inspect($medicine, $this->visit)));
        $selectedMedicine = $this->prescriptionItemForm->medicine_id
            ? Medicine::query()->forCurrentFacility()->with(['dosageForm', 'dispensingUnit'])->find($this->prescriptionItemForm->medicine_id)
            : null;

        return view('livewire.opd.consultation', [
            'labTests' => LaboratoryTest::query()->forCurrentFacility()->with(['service', 'category', 'specimenType'])->where('is_active', true)->whereHas('service', fn ($query) => $query->where('is_active', true))->orderBy('name')->get(),
            'labServices' => Service::query()->forCurrentFacility()->where('service_type', 'laboratory_test')->where('is_active', true)->get(),
            'procedureServices' => Service::query()->forCurrentFacility()->where('service_type', 'procedure')->where('is_active', true)->get(),
            'medicines' => $medicines,
            'doseOptions' => MedicationDirections::doseOptions($selectedMedicine),
            'admissionConfigured' => Department::query()->forCurrentFacility()->where('code', 'BED')->where('is_active', true)->where('can_receive_patients', true)->where('queue_enabled', true)->exists(),
            'canViewLaboratoryResults' => $canViewLaboratoryResults,
        ])->layout('components.layouts.app', ['title' => 'OPD Consultation', 'description' => $this->visit->patient->fullName().' - '.$this->visit->visit_number]);
    }
}
