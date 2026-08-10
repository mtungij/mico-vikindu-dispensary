<?php

namespace App\Livewire\Patients;

use App\Enums\Gender;
use App\Enums\PatientStatus;
use App\Enums\PayerType;
use App\Livewire\Forms\PatientPayerForm;
use App\Livewire\Forms\PatientPersonalForm;
use App\Livewire\Forms\VisitForm;
use App\Models\ActivityLog;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

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

    public bool $showStrongDuplicateOverride = false;

    public string $duplicateOverrideReason = '';

    public ?string $duplicateReviewFingerprint = null;

    public string $activeVisitOverrideReason = '';

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
        $this->showStrongDuplicateOverride = false;
        $this->duplicateOverrideReason = '';
        $this->duplicateReviewFingerprint = null;
        $this->activeVisitOverrideReason = '';
        $this->newPatientConsultationServiceId = null;
        $this->showModal = true;
        $this->refreshChargePreview();
    }

    public function searchDuplicates(PatientDuplicateDetectionService $detector): void
    {
        $this->validateOnly('personal.primary_phone');
        $this->duplicates = $detector->detect($this->personal->all(), $this->payer->all());
        $this->resetDuplicateReview();
        if (($this->duplicates['status'] ?? 'none') !== 'none') {
            ActivityLog::query()->create([
                'user_id' => auth()->id(),
                'event' => 'patient_duplicate_warning_shown',
                'subject_type' => Patient::class,
                'subject_id' => $this->duplicates['matched_patient_ids'][0] ?? null,
                'new_values' => [
                    'facility_id' => currentFacility()?->id,
                    'matched_patient_ids' => $this->duplicates['matched_patient_ids'],
                    'match_severity' => $this->duplicates['status'],
                    'match_reasons' => $this->duplicates['reasons'],
                ],
            ]);
        }
    }

    public function updatedPatientLookup(PatientSearchService $search): void
    {
        $this->patientMatches = $search->searchWithReasons($this->patientLookup)->map(function (array $searchMatch): array {
            $patient = $searchMatch['patient'];

            return [
                'id' => $patient->id, 'name' => $patient->fullName(), 'patient_number' => $patient->patient_number,
                'age' => $patient->ageLabel(), 'sex' => $patient->gender?->label(), 'phone' => $patient->primary_phone,
                'nida' => $patient->nida_number, 'last_visit' => $patient->latestVisit?->visit_number,
                'active_visit' => $patient->activeVisit?->visit_number, 'active_visit_id' => $patient->activeVisit?->id,
                'payer' => $patient->primaryPayerProfile?->payer_type?->label(), 'status' => $patient->patient_status?->label(),
                'match_reason' => $searchMatch['reason'],
                'active_visit_url' => $patient->activeVisit ? $this->activeVisitUrl($patient) : null,
            ];
        })->all();
    }

    public function selectExistingPatient(int $patientId): void
    {
        $patient = Patient::query()->forCurrentFacility()->with(['primaryPayerProfile', 'activeVisit'])->findOrFail($patientId);
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
        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'event' => 'existing_patient_selected',
            'subject_type' => $patient::class,
            'subject_id' => $patient->id,
            'new_values' => ['facility_id' => $patient->facility_id, 'patient_id' => $patient->id, 'active_visit_id' => $patient->activeVisit?->id],
        ]);
    }

    public function clearSelectedPatient(): void
    {
        $this->resetErrorBag();
        $this->selectedPatientId = null;
        $this->visit->visit_type = 'new_patient';
        $this->activeVisitOverrideReason = '';
        $this->refreshChargePreview();
        $this->step = 1;
    }

    public function confirmDifferentPatient(PatientDuplicateDetectionService $detector): void
    {
        $this->duplicates = $detector->detect($this->personal->all(), $this->payer->all());
        if (($this->duplicates['status'] ?? 'none') === 'exact') {
            $this->addError('duplicate', 'Mgonjwa huyu anaonekana tayari yupo kwenye mfumo. Chagua rekodi iliyopo.');

            return;
        }
        if (($this->duplicates['status'] ?? 'none') === 'probable' && mb_strlen(trim($this->duplicateOverrideReason)) < 5) {
            $this->addError('duplicateOverrideReason', 'Eleza kwa nini huyu ni mgonjwa tofauti.');

            return;
        }
        $this->confirmDuplicateCreation = true;
        $this->duplicateReviewFingerprint = $this->duplicateFingerprint();
    }

    public function requestDuplicateOverride(): void
    {
        Gate::authorize('patients.override-duplicate-warning');
        $this->showStrongDuplicateOverride = true;
    }

    public function confirmDuplicateOverride(PatientDuplicateDetectionService $detector): void
    {
        Gate::authorize('patients.override-duplicate-warning');
        $this->duplicates = $detector->detect($this->personal->all(), $this->payer->all());
        if (($this->duplicates['status'] ?? 'none') !== 'exact') {
            $this->confirmDifferentPatient($detector);

            return;
        }
        if (mb_strlen(trim($this->duplicateOverrideReason)) < 10) {
            $this->addError('duplicateOverrideReason', 'Eleza sababu yenye maana kwa angalau herufi 10.');

            return;
        }
        $this->confirmDuplicateCreation = true;
        $this->duplicateReviewFingerprint = $this->duplicateFingerprint();
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
        if ($this->selectedPatientId) {
            $this->visit->visit_type = 'returning_patient';
        }
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
                $personalData = $this->personal->data();
                $payerData = $this->payer->data();
                $duplicates = app(PatientDuplicateDetectionService::class)->detect($personalData, $payerData);
                $reviewIsCurrent = $this->confirmDuplicateCreation && hash_equals((string) $this->duplicateReviewFingerprint, $this->duplicateFingerprint());
                if ($duplicates['status'] !== 'none' && ! $reviewIsCurrent) {
                    $this->duplicates = $duplicates;
                    $message = $duplicates['status'] === 'exact'
                        ? 'Mgonjwa huyu anaonekana tayari yupo kwenye mfumo. Chagua rekodi iliyopo.'
                        : 'Kuna mgonjwa anayefanana. Hakiki rekodi iliyopo kabla ya kuunda mpya.';
                    $this->addError('duplicate', $message);
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
                ? $workflow->openReturningPatientVisit($patient, $this->payer->data(), [...$this->visit->data(), 'visit_type' => 'returning_patient'], $this->selectedLaboratoryTestIds, auth()->user(), $this->activeVisitOverrideReason)
                : $workflow->registerNewPatientAndVisit($personalData, $payerData, $this->visit->data(), $this->selectedLaboratoryTestIds, auth()->user(), [
                    'confirmed' => $reviewIsCurrent,
                    'reason' => $this->duplicateOverrideReason,
                ]);
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
            ? Patient::query()->forCurrentFacility()->with(['latestVisit', 'activeVisit.currentDepartment', 'activeVisit.invoice', 'activeVisit.laboratoryOrders'])->find($this->selectedPatientId)
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

    public function activeVisitUrl(?Patient $patient): string
    {
        $visit = $patient?->activeVisit;
        if (! $visit) {
            return route('patients.show', $patient);
        }
        if ($visit->visit_status->value === 'awaiting_payment' && $visit->invoice && auth()->user()->can('billing.view-invoice')) {
            return route('billing.invoices.show', $visit->invoice);
        }
        if ($visit->currentDepartment?->code === 'OPD' && auth()->user()->can('opd.consult')) {
            return route('opd.consultation', $visit);
        }
        if ($visit->currentDepartment?->code === 'LAB' && $visit->laboratoryOrders->isNotEmpty() && auth()->user()->can('laboratory.view-order')) {
            return route('laboratory.orders.show', $visit->laboratoryOrders->last());
        }
        if ($visit->currentDepartment?->code === 'PHA' && auth()->user()->can('pharmacy.view-queue')) {
            return route('pharmacy.index');
        }

        return route('patients.show', $patient);
    }

    private function duplicateFingerprint(): string
    {
        return hash('sha256', json_encode([
            'first_name' => mb_strtolower(trim((string) $this->personal->first_name)),
            'last_name' => mb_strtolower(trim((string) $this->personal->last_name)),
            'date_of_birth' => $this->personal->date_of_birth,
            'age_years' => $this->personal->age_years,
            'gender' => $this->personal->gender,
            'primary_phone' => trim((string) $this->personal->primary_phone),
            'nida_number' => mb_strtolower(trim((string) $this->personal->nida_number)),
            'passport_number' => mb_strtolower(trim((string) $this->personal->passport_number)),
            'membership_number' => mb_strtolower(trim((string) $this->payer->membership_number)),
        ], JSON_THROW_ON_ERROR));
    }

    private function resetDuplicateReview(): void
    {
        $this->confirmDuplicateCreation = false;
        $this->showStrongDuplicateOverride = false;
        $this->duplicateReviewFingerprint = null;
    }
}
