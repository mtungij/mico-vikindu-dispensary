<?php

namespace App\Livewire\Dental;

use App\Livewire\Forms\DentalDiagnosisForm;
use App\Livewire\Forms\DentalEncounterForm;
use App\Livewire\Forms\DentalProcedureForm;
use App\Livewire\Forms\DentalTreatmentPlanForm;
use App\Livewire\Forms\PrescriptionItemForm;
use App\Models\ClinicalEncounter;
use App\Models\DentalEncounter;
use App\Models\Medicine;
use App\Models\PrescriptionItem;
use App\Models\Service;
use App\Models\Visit;
use App\Services\ClinicalEncounterService;
use App\Services\DentalDiagnosisService;
use App\Services\DentalEncounterService;
use App\Services\DentalProcedureService;
use App\Services\DentalTreatmentPlanService;
use App\Services\MedicineBillingReadinessService;
use App\Services\PrescriptionService;
use App\Support\MedicationDirections;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Consultation extends Component
{
    public Visit $visit;

    public DentalEncounter $dentalEncounter;

    public DentalEncounterForm $form;

    public DentalDiagnosisForm $diagnosisForm;

    public DentalTreatmentPlanForm $planForm;

    public DentalProcedureForm $procedureForm;

    public PrescriptionItemForm $prescriptionItemForm;

    public string $activeTab = 'summary';

    public bool $showDiagnosisModal = false;

    public bool $showPlanModal = false;

    public bool $showProcedureModal = false;

    public string $saveState = '';

    public ?int $editingPrescriptionItemId = null;

    public string $medicineSearch = '';

    public function mount(Visit $visit, DentalEncounterService $service): void
    {
        Gate::authorize('dental.consult');
        abort_unless($visit->facility_id === currentFacility()?->id && auth()->user()?->belongsToCurrentFacility(), 404);
        $this->visit = $visit->load(['patient', 'invoice.items', 'payerProfile']);
        $this->dentalEncounter = DentalEncounter::query()->where('visit_id', $visit->id)
            ->whereNotIn('status', ['completed', 'cancelled', 'referred'])->first()
            ?: $service->start($visit, auth()->user());
        $this->form->fillFromModel($this->dentalEncounter);
    }

    public function autosave(DentalEncounterService $service): void
    {
        $this->saveState = 'Inahifadhi...';
        $this->dentalEncounter = $service->saveDraft($this->dentalEncounter, $this->form->normalize(), auth()->user());
        $this->saveState = 'Imehifadhiwa';
    }

    public function addDiagnosis(DentalDiagnosisService $service): void
    {
        Gate::authorize('dental.create-diagnosis');
        $service->add($this->dentalEncounter, $this->diagnosisForm->normalize(), auth()->user());
        $this->diagnosisForm->resetForm();
        $this->showDiagnosisModal = false;
        Notifier::success('Diagnosis imeongezwa.');
    }

    public function createPlan(DentalTreatmentPlanService $service): void
    {
        Gate::authorize('dental.create-treatment-plan');
        $service->createPlan($this->dentalEncounter, $this->planForm->normalize(), auth()->user());
        $this->planForm->resetForm();
        $this->showPlanModal = false;
        Notifier::success('Treatment plan imeundwa.');
    }

    public function createProcedure(DentalProcedureService $service): void
    {
        $data = $this->procedureForm->normalize();
        $dentalService = Service::query()->where('facility_id', currentFacility()?->id)->where('id', $data['service_id'])->firstOrFail();
        $service->createProcedure($this->dentalEncounter, $dentalService, $data, auth()->user());
        $this->procedureForm->resetForm();
        $this->showProcedureModal = false;
        Notifier::success('Procedure imeanza na charge imeongezwa.');
    }

    public function addPrescription(ClinicalEncounterService $service): void
    {
        Gate::authorize('prescriptions.create');
        $this->synchronizeMedicationForm();
        $this->prescriptionItemForm->validate();
        try {
            $service->addPrescription($this->medicationEncounter(), ['items' => [$this->prescriptionItemForm->normalize()]], auth()->user());
        } catch (ValidationException $exception) {
            $this->showMedicineValidationFailure($exception);

            return;
        }
        $this->prescriptionItemForm->resetForm();
        Notifier::success('Dawa imeongezwa.');
    }

    public function editPrescriptionItem(int $prescriptionItemId, PrescriptionService $service): void
    {
        $item = $this->encounterPrescriptionItem($prescriptionItemId);
        $service->assertItemEditable($item, auth()->user());
        $this->prescriptionItemForm->fillFromModel($item);
        $this->editingPrescriptionItemId = $item->id;
        $this->activeTab = 'medicines';
    }

    public function updatePrescriptionItem(PrescriptionService $service): void
    {
        $this->synchronizeMedicationForm();
        $this->prescriptionItemForm->validate();
        try {
            $service->updateItem($this->encounterPrescriptionItem((int) $this->editingPrescriptionItemId), $this->prescriptionItemForm->normalize(), auth()->user());
        } catch (ValidationException $exception) {
            $this->showMedicineValidationFailure($exception);

            return;
        }
        $this->cancelPrescriptionEdit();
        Notifier::success('Dawa imesasishwa.');
    }

    public function removePrescriptionItem(int $prescriptionItemId, PrescriptionService $service): void
    {
        $service->removeItem($this->encounterPrescriptionItem($prescriptionItemId), auth()->user());
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
        $medicine = $medicineId ? Medicine::query()->forCurrentFacility()->with(['dosageForm', 'route'])->find($medicineId) : null;
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
        $calculated = MedicationDirections::calculateQuantity($this->prescriptionItemForm->dose, $this->prescriptionItemForm->frequency, $this->prescriptionItemForm->duration_value, $this->prescriptionItemForm->duration_unit);
        $this->prescriptionItemForm->quantity_manually_adjusted = filled($quantity) && ($calculated === null || abs((float) $quantity - $calculated) > 0.005);
    }

    public function complete(DentalEncounterService $service): mixed
    {
        Gate::authorize('complete', $this->dentalEncounter);
        $service->saveDraft($this->dentalEncounter, $this->form->normalize(), auth()->user());
        $service->complete($this->dentalEncounter, auth()->user());
        Notifier::success('Dental encounter imekamilishwa. Medicine charges, if any, were sent to Billing.');

        return redirect()->route('dental.index');
    }

    private function medicationEncounter(): ClinicalEncounter
    {
        return $this->dentalEncounter->clinicalEncounter()->firstOrFail();
    }

    private function encounterPrescriptionItem(int $id): PrescriptionItem
    {
        return PrescriptionItem::query()->whereHas('prescription', fn ($query) => $query
            ->where('clinical_encounter_id', $this->dentalEncounter->clinical_encounter_id)
            ->where('facility_id', currentFacility()?->id))->findOrFail($id);
    }

    private function synchronizeMedicationForm(): void
    {
        if (! $this->prescriptionItemForm->quantity_manually_adjusted) {
            $this->recalculateMedicationQuantity();
        }
    }

    private function recalculateMedicationQuantity(): void
    {
        $quantity = MedicationDirections::calculateQuantity($this->prescriptionItemForm->dose, $this->prescriptionItemForm->frequency, $this->prescriptionItemForm->duration_value, $this->prescriptionItemForm->duration_unit);
        $this->prescriptionItemForm->quantity_manually_adjusted = false;
        if ($quantity === null) {
            $this->prescriptionItemForm->quantity = null;
            $this->prescriptionItemForm->calculation_summary = null;

            return;
        }
        $this->prescriptionItemForm->quantity = rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
        $this->prescriptionItemForm->calculation_summary = sprintf('%s × %s × %s %s = %s', $this->prescriptionItemForm->dose, MedicationDirections::displayFrequency($this->prescriptionItemForm->frequency), $this->prescriptionItemForm->duration_value, str($this->prescriptionItemForm->duration_unit)->replace('_', ' ')->toString(), $this->prescriptionItemForm->quantity);
    }

    private function resetCalculatedQuantity(): void
    {
        $this->prescriptionItemForm->quantity = null;
        $this->prescriptionItemForm->quantity_manually_adjusted = false;
        $this->prescriptionItemForm->calculation_summary = null;
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

    public function render(): View
    {
        $this->dentalEncounter->load(['patient', 'clinicalEncounter.prescriptions.items.medicine.dispensingUnit', 'toothRecords.findings.type', 'diagnoses', 'treatmentPlans.items', 'procedures.service', 'attachments', 'labOrders']);
        $medicines = Medicine::query()->forCurrentFacility()->with(['generic', 'dosageForm', 'dispensingUnit', 'route', 'service'])
            ->when(strlen($this->medicineSearch) >= 2, fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$this->medicineSearch.'%')->orWhere('brand_name', 'like', '%'.$this->medicineSearch.'%')->orWhereHas('generic', fn ($g) => $g->where('name', 'like', '%'.$this->medicineSearch.'%'))))
            ->orderByDesc('is_active')->orderBy('name')->limit(50)->get();
        $readiness = app(MedicineBillingReadinessService::class);
        $medicines->each(fn (Medicine $medicine) => $medicine->setAttribute('billing_readiness', $readiness->inspect($medicine, $this->visit)));
        $selectedMedicine = $this->prescriptionItemForm->medicine_id
            ? Medicine::query()->forCurrentFacility()->with(['dosageForm', 'dispensingUnit'])->find($this->prescriptionItemForm->medicine_id)
            : null;

        return view('livewire.dental.consultation', [
            'dentalServices' => Service::query()->forCurrentFacility()->whereIn('service_type', ['dental_service', 'procedure'])->where('is_active', true)->orderBy('name')->get(),
            'medicines' => $medicines,
            'doseOptions' => MedicationDirections::doseOptions($selectedMedicine),
            'prescriptions' => $this->dentalEncounter->clinicalEncounter->prescriptions,
            'isReadOnly' => $this->dentalEncounter->isCompleted(),
        ])->layout('components.layouts.app', ['title' => 'Dental Consultation', 'description' => $this->visit->patient->fullName().' - '.$this->visit->visit_number]);
    }
}
