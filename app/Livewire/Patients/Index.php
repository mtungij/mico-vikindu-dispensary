<?php

namespace App\Livewire\Patients;

use App\Enums\Gender;
use App\Enums\PayerType;
use App\Enums\PatientStatus;
use App\Livewire\Forms\PatientPayerForm;
use App\Livewire\Forms\PatientPersonalForm;
use App\Livewire\Forms\VisitForm;
use App\Models\Department;
use App\Models\InsuranceProvider;
use App\Models\CorporateAccount;
use App\Models\Patient;
use App\Models\Service;
use App\Services\PatientDuplicateDetectionService;
use App\Services\ReceptionChargeService;
use App\Services\ReceptionWorkflowService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public PatientPersonalForm $personal; public PatientPayerForm $payer; public VisitForm $visit;
    public string $search = ''; public string $gender = ''; public string $status = ''; public string $payerType = ''; public bool $showModal = false; public int $step = 1; public array $duplicates = []; public array $chargePreview = [];
    public function mount(): void { Gate::authorize('viewAny', Patient::class); }
    public function create(): void { Gate::authorize('create', Patient::class); $this->personal->resetForm(); $this->payer->reset(); $this->visit->reset(); $this->payer->payer_type = 'cash'; $this->payer->coverage_status = 'active'; $this->visit->visit_type = 'new_patient'; $this->visit->payer_type = 'cash'; $this->visit->priority = 'normal'; $this->visit->source = 'walk_in'; $this->step = 1; $this->duplicates = []; $this->chargePreview = []; $this->showModal = true; $this->refreshChargePreview(); }
    public function searchDuplicates(PatientDuplicateDetectionService $detector): void { $this->duplicates = $detector->detect($this->personal->data()); }
    public function nextStep(): void { if ($this->step === 2) $this->personal->validate(); if ($this->step === 4) $this->payer->validate(); if ($this->step === 5) { $this->visit->validate(); $this->refreshChargePreview(); } $this->step = min(6, $this->step + 1); }
    public function previousStep(): void { $this->step = max(1, $this->step - 1); }
    public function updatedPayerPayerType(): void { if ($this->payer->payer_type !== 'insurance') $this->payer->insurance_provider_id = null; if ($this->payer->payer_type !== 'corporate') $this->payer->corporate_account_id = null; $this->visit->payer_type = $this->payer->payer_type; $this->refreshChargePreview(); }
    public function updatedPayerInsuranceProviderId(): void { $this->refreshChargePreview(); }
    public function updatedPayerCorporateAccountId(): void { $this->refreshChargePreview(); }
    public function updatedVisitDestinationDepartmentId(): void { $this->visit->consultation_service_id = null; $this->refreshChargePreview(); }
    public function updatedVisitConsultationServiceId(): void { $this->refreshChargePreview(); }
    public function updatedVisitVisitType(): void { $this->refreshChargePreview(); }
    public function refreshChargePreview(): void
    {
        $facility = currentFacility();
        if (! $facility) {
            $this->chargePreview = [];
            return;
        }
        $this->chargePreview = app(ReceptionChargeService::class)->buildChargePreview($facility, true, $this->visit->destination_department_id, $this->visit->consultation_service_id, [
            'payer_type' => $this->payer->payer_type,
            'insurance_provider_id' => $this->payer->insurance_provider_id,
            'corporate_account_id' => $this->payer->corporate_account_id,
            'require_payment_before_service' => $this->visit->require_payment_before_service,
        ]);
    }
    public function save(ReceptionWorkflowService $workflow): void
    {
        Gate::authorize('create', Patient::class);
        $result = $workflow->registerNewPatientAndVisit($this->personal->data(), $this->payer->data(), $this->visit->data(), [], auth()->user());
        $this->showModal = false; Notifier::success('patients.created'); $this->redirectRoute('patients.show', $result['patient']);
    }
    public function render(): View
    {
        $patients = Patient::query()->forCurrentFacility()->with(['primaryPayerProfile', 'latestVisit', 'activeVisit'])
            ->when($this->search, fn($q) => $q->where(fn($q) => $q->where('patient_number','like',"%{$this->search}%")->orWhere('first_name','like',"%{$this->search}%")->orWhere('last_name','like',"%{$this->search}%")->orWhere('primary_phone','like',"%{$this->search}%")->orWhere('nida_number','like',"%{$this->search}%")))
            ->when($this->gender, fn($q) => $q->where('gender', $this->gender))->when($this->status, fn($q) => $q->where('patient_status', $this->status))
            ->when($this->payerType, fn($q) => $q->whereHas('primaryPayerProfile', fn($q) => $q->where('payer_type', $this->payerType)))
            ->latest()->paginate(10);
        $departments = Department::query()->forCurrentFacility()->where('is_active', true)->where('can_receive_patients', true)->orderBy('sort_order')->get();
        $consultationServices = Service::query()->forCurrentFacility()->where('is_active', true)->where('service_type', 'consultation')->when($this->visit->destination_department_id, fn ($q) => $q->where('department_id', $this->visit->destination_department_id))->orderBy('name')->get();
        return view('livewire.patients.index', ['patients' => $patients, 'genders' => Gender::cases(), 'statuses' => PatientStatus::cases(), 'payerTypes' => PayerType::cases(), 'departments' => $departments, 'services' => $consultationServices, 'providers' => InsuranceProvider::query()->forCurrentFacility()->where('is_active', true)->get(), 'corporates' => CorporateAccount::query()->forCurrentFacility()->where('is_active', true)->get()])
            ->layout('components.layouts.app', ['title' => 'Wagonjwa', 'description' => 'Sajili wagonjwa na fungua visits.']);
    }
}
