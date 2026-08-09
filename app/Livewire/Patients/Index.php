<?php

namespace App\Livewire\Patients;

use App\Enums\Gender;
use App\Enums\PatientStatus;
use App\Enums\PayerType;
use App\Livewire\Forms\PatientPayerForm;
use App\Livewire\Forms\PatientPersonalForm;
use App\Livewire\Forms\VisitForm;
use App\Models\CorporateAccount;
use App\Models\Department;
use App\Models\InsuranceProvider;
use App\Models\LaboratoryTest;
use App\Models\Patient;
use App\Models\Service;
use App\Services\PatientDuplicateDetectionService;
use App\Services\PatientSearchService;
use App\Services\ReceptionChargeService;
use App\Services\ReceptionWorkflowService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Throwable;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public PatientPersonalForm $personal;

    public PatientPayerForm $payer;

    public VisitForm $visit;

    public string $search = '';

    public string $gender = '';

    public string $status = '';

    public string $payerType = '';

    public bool $showModal = false;

    public int $step = 1;

    public array $duplicates = [];

    public array $chargePreview = [];

    public array $selectedLaboratoryTestIds = [];

    public string $patientLookup = '';

    public array $patientMatches = [];

    public ?int $selectedPatientId = null;

    public bool $confirmDuplicateCreation = false;

    public ?int $newPatientConsultationServiceId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Patient::class);
    }

    public function create(): void
    {
        Gate::authorize('create', Patient::class);
        $this->personal->resetForm();
        $this->payer->reset();
        $this->visit->reset();
        $this->payer->payer_type = 'cash';
        $this->payer->coverage_status = 'active';
        $this->visit->visit_type = 'new_patient';
        $this->visit->payer_type = 'cash';
        $this->visit->priority = 'normal';
        $this->visit->source = 'walk_in';
        $this->visit->registration_idempotency_key = (string) Str::uuid();
        $this->step = 1;
        $this->duplicates = [];
        $this->selectedLaboratoryTestIds = [];
        $this->chargePreview = [];
        $this->patientLookup = '';
        $this->patientMatches = [];
        $this->selectedPatientId = null;
        $this->confirmDuplicateCreation = false;
        $this->newPatientConsultationServiceId = null;
        $this->showModal = true;
        $this->refreshChargePreview();
    }

    public function searchDuplicates(PatientDuplicateDetectionService $detector): void
    {
        $this->duplicates = $detector->detect($this->personal->all());
    }

    public function updatedPatientLookup(PatientSearchService $search): void
    {
        $this->patientMatches = $search->search($this->patientLookup)->map(fn (Patient $patient) => [
            'id' => $patient->id, 'name' => $patient->fullName(), 'patient_number' => $patient->patient_number,
            'age' => $patient->ageLabel(), 'sex' => $patient->gender?->label(), 'phone' => $patient->primary_phone,
            'nida' => $patient->nida_number, 'last_visit' => $patient->latestVisit?->visit_number,
            'active_visit' => $patient->activeVisit?->visit_number, 'active_visit_id' => $patient->activeVisit?->id,
            'payer' => $patient->primaryPayerProfile?->payer_type?->label(), 'status' => $patient->patient_status?->label(),
        ])->all();
    }

    public function selectExistingPatient(int $patientId): void
    {
        $patient = Patient::query()->forCurrentFacility()->with('primaryPayerProfile')->findOrFail($patientId);
        Gate::authorize('view', $patient);
        $this->resetErrorBag();
        $this->selectedPatientId = $patient->id;
        $this->newPatientConsultationServiceId = $this->visit->consultation_service_id;
        $this->visit->visit_type = 'returning_patient';
        $this->visit->consultation_service_id = null;
        if ($profile = $patient->primaryPayerProfile) {
            foreach (array_keys($this->payer->rules()) as $field) {
                $value = $profile->{$field};
                $this->payer->{$field} = $value instanceof \BackedEnum ? $value->value : $value;
            }
        }
        $this->refreshChargePreview();
        $this->step = 4;
    }

    public function clearSelectedPatient(): void
    {
        $this->resetErrorBag();
        $this->selectedPatientId = null;
        $this->visit->visit_type = 'new_patient';
        $this->refreshChargePreview();
    }

    public function nextStep(): void
    {
        if ($this->step === 2) {
            $this->personal->validate();
        }
        if ($this->step === 4) {
            $this->payer->validate();
        }
        if ($this->step === 5) {
            $this->visit->validate();
            if ($this->returningPatientIsMissing()) {
                $this->showMissingReturningPatient();

                return;
            }
            if ($this->isDirectLaboratory() && $this->selectedLaboratoryTestIds === []) {
                $this->addError('selectedLaboratoryTestIds', 'Chagua angalau kipimo kimoja cha maabara.');

                return;
            }
            $this->refreshChargePreview();
        }
        $this->step = min(6, $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function updatedPayerPayerType(): void
    {
        if ($this->payer->payer_type !== 'insurance') {
            $this->payer->insurance_provider_id = null;
        } if ($this->payer->payer_type !== 'corporate') {
            $this->payer->corporate_account_id = null;
        } $this->visit->payer_type = $this->payer->payer_type;
        $this->refreshChargePreview();
    }

    public function updatedPayerInsuranceProviderId(): void
    {
        $this->refreshChargePreview();
    }

    public function updatedPayerCorporateAccountId(): void
    {
        $this->refreshChargePreview();
    }

    public function updatedVisitDestinationDepartmentId(): void
    {
        $this->visit->consultation_service_id = null;
        if (! $this->isDirectLaboratory()) {
            $this->selectedLaboratoryTestIds = [];
        } $this->refreshChargePreview();
    }

    public function updatedSelectedLaboratoryTestIds(): void
    {
        $this->selectedLaboratoryTestIds = collect($this->selectedLaboratoryTestIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        $this->refreshChargePreview();
    }

    public function updatedVisitConsultationServiceId(): void
    {
        if ($this->visit->visit_type === 'new_patient') {
            $this->newPatientConsultationServiceId = $this->visit->consultation_service_id;
        }
        $this->refreshChargePreview();
    }

    public function updatedVisitVisitType(): void
    {
        if ($this->selectedPatientId) $this->visit->visit_type = 'returning_patient';
        if ($this->visit->visit_type === 'new_patient') {
            $this->visit->consultation_service_id = $this->newPatientConsultationServiceId;
        } else {
            $this->newPatientConsultationServiceId ??= $this->visit->consultation_service_id;
            $this->visit->consultation_service_id = null;
        }
        $this->refreshChargePreview();
    }

    public function refreshChargePreview(): void
    {
        $facility = currentFacility();
        if (! $facility) {
            $this->chargePreview = [];

            return;
        }
        $isNewPatient = $this->selectedPatientId === null && $this->visit->visit_type === 'new_patient';
        $this->chargePreview = app(ReceptionChargeService::class)->buildChargePreview($facility, $isNewPatient, $this->visit->destination_department_id, $this->visit->consultation_service_id, [
            'payer_type' => $this->payer->payer_type,
            'insurance_provider_id' => $this->payer->insurance_provider_id,
            'corporate_account_id' => $this->payer->corporate_account_id,
            'require_payment_before_service' => $this->visit->require_payment_before_service,
        ], $this->selectedLaboratoryTestIds, $this->visit->visit_type);
    }

    public function save(ReceptionWorkflowService $workflow): mixed
    {
        $this->resetErrorBag();
        if ($this->returningPatientIsMissing()) {
            $this->showMissingReturningPatient();

            return null;
        }

        try {
            if ($this->selectedPatientId) {
                Gate::authorize('reception.open-visit');
                $patient = Patient::query()->forCurrentFacility()->with(['primaryPayerProfile', 'activeVisit'])->find($this->selectedPatientId);
                if (! $patient) {
                    $this->showMissingReturningPatient();

                    return null;
                }
                Gate::authorize('view', $patient);
                if ($patient->activeVisit && ! auth()->user()->can('reception.override-active-visit')) {
                    throw ValidationException::withMessages([
                        'activeVisit' => 'Mgonjwa huyu tayari ana active visit '.$patient->activeVisit->visit_number.'.',
                    ]);
                }
            } else {
                Gate::authorize('create', Patient::class);
                $duplicates = app(PatientDuplicateDetectionService::class)->detect($this->personal->data());
                if ($duplicates['status'] !== 'none' && ! $this->confirmDuplicateCreation) {
                    $this->duplicates = $duplicates;
                    $this->addError('duplicate', 'Kuna mgonjwa anayefanana. Chagua mgonjwa aliyepo au thibitisha kuunda rekodi mpya.');
                    $this->step = 1;
                    Notifier::warning('Tafadhali rekebisha taarifa zifuatazo.');

                    return null;
                }
            }
            if ($this->isDirectLaboratory() && $this->selectedLaboratoryTestIds === []) {
                throw ValidationException::withMessages([
                    'selectedLaboratoryTestIds' => 'Chagua angalau kipimo kimoja cha maabara.',
                ]);
            }
            $result = $this->selectedPatientId
                ? $workflow->openReturningPatientVisit($patient, $this->payer->data(), [...$this->visit->data(), 'visit_type' => 'returning_patient'], $this->selectedLaboratoryTestIds, auth()->user())
                : $workflow->registerNewPatientAndVisit($this->personal->data(), $this->payer->data(), $this->visit->data(), $this->selectedLaboratoryTestIds, auth()->user());
        } catch (ValidationException $exception) {
            $this->showValidationFailure($exception);

            return null;
        } catch (AuthorizationException) {
            $message = 'Huna ruhusa ya kusajili visit hii.';
            $this->addError('authorization', $message);
            Notifier::error($message);

            return null;
        } catch (Throwable $exception) {
            report($exception);
            $message = 'Imeshindikana kuhifadhi taarifa. Tafadhali jaribu tena.';
            $this->addError('save', $message);
            Notifier::error($message);

            return null;
        }

        $this->showModal = false;
        Notifier::success('patients.created');

        return $this->redirectRoute('patients.show', $result['patient']);
    }

    public function render(): View
    {
        $patients = Patient::query()->forCurrentFacility()->with(['primaryPayerProfile', 'latestVisit', 'activeVisit'])
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('patient_number', 'like', "%{$this->search}%")->orWhere('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")->orWhere('primary_phone', 'like', "%{$this->search}%")->orWhere('nida_number', 'like', "%{$this->search}%")))
            ->when($this->gender, fn ($q) => $q->where('gender', $this->gender))->when($this->status, fn ($q) => $q->where('patient_status', $this->status))
            ->when($this->payerType, fn ($q) => $q->whereHas('primaryPayerProfile', fn ($q) => $q->where('payer_type', $this->payerType)))
            ->latest()->paginate(10);
        $departments = Department::query()->forCurrentFacility()->where('is_active', true)->where('can_receive_patients', true)->orderBy('sort_order')->get();
        $consultationServices = Service::query()->forCurrentFacility()->where('is_active', true)->where('service_type', 'consultation')->when($this->visit->destination_department_id, fn ($q) => $q->where('department_id', $this->visit->destination_department_id))->orderBy('name')->get();
        $laboratoryTests = LaboratoryTest::query()->forCurrentFacility()->with(['service', 'specimenType'])->where('is_active', true)->whereHas('service', fn ($query) => $query->where('is_active', true))->orderBy('name')->get();

        $selectedPatient = $this->selectedPatientId
            ? Patient::query()->forCurrentFacility()->with(['latestVisit', 'activeVisit'])->find($this->selectedPatientId)
            : null;

        return view('livewire.patients.index', ['patients' => $patients, 'selectedPatient' => $selectedPatient, 'genders' => Gender::cases(), 'statuses' => PatientStatus::cases(), 'payerTypes' => PayerType::cases(), 'departments' => $departments, 'services' => $consultationServices, 'laboratoryTests' => $laboratoryTests, 'providers' => InsuranceProvider::query()->forCurrentFacility()->where('is_active', true)->get(), 'corporates' => CorporateAccount::query()->forCurrentFacility()->where('is_active', true)->get()])
            ->layout('components.layouts.app', ['title' => 'Wagonjwa', 'description' => 'Sajili wagonjwa na fungua visits.']);
    }

    public function isDirectLaboratory(): bool
    {
        return Department::query()->forCurrentFacility()->whereKey($this->visit->destination_department_id)->where('code', 'LAB')->exists();
    }

    public function returningPatientIsMissing(): bool
    {
        return $this->visit->visit_type === 'returning_patient' && ! $this->selectedPatientId;
    }

    private function showMissingReturningPatient(): void
    {
        $this->addError('selectedPatientId', 'Returning visit requires selecting an existing patient first.');
        $this->addError('save', 'Chagua mgonjwa wa zamani kabla ya kuhifadhi Returning Visit.');
        $this->step = 1;
        Notifier::warning('Returning visit requires an existing patient.');
    }

    private function showValidationFailure(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $this->addError($field, $message);
            }
        }
        Notifier::warning('Tafadhali rekebisha taarifa zifuatazo.');
    }
}
