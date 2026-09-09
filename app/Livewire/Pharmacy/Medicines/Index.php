<?php

namespace App\Livewire\Pharmacy\Medicines;

use App\Enums\PayerType;
use App\Livewire\Forms\MedicineForm;
use App\Models\DosageForm;
use App\Models\GenericMedicine;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineRoute;
use App\Models\MedicineUnit;
use App\Models\Service;
use App\Services\MedicineBillingReadinessService;
use App\Services\MedicineBillingSetupService;
use App\Services\MedicineCatalogService;
use App\Services\ServicePricingService;
use App\Support\Notifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class Index extends Component
{
    use WithPagination;

    public MedicineForm $form;

    public bool $showModal = false;

    public string $search = '';

    public string $billingStatus = '';

    public string $catalogStatus = 'active';

    public bool $showDeleteModal = false;

    public ?int $deletingMedicineId = null;

    public string $deletingMedicineName = '';

    public string $deletingMedicineUnit = '';

    public array $deletionAssessment = [];

    public function mount(): void
    {
        Gate::authorize('pharmacy.manage-medicines');
    }

    public function create(): void
    {
        $this->form->resetForm();
        $this->showModal = true;
    }

    public function edit(Medicine $medicine, MedicineBillingSetupService $billingSetup): void
    {
        abort_unless($medicine->facility_id === currentFacility()?->id, 404);
        $medicine->load('service');
        $this->form->fillFromModel($medicine);
        $prices = $medicine->service
            ? app(ServicePricingService::class)->currentPriceQuery($medicine->service, PayerType::Cash)->get()
            : collect();
        $this->form->cash_price = $prices->count() === 1 ? (string) $prices->first()->amount : null;
        $this->form->use_custom_billing_service = $medicine->service !== null && ! $billingSetup->isSystemManaged($medicine);
        $this->showModal = true;
    }

    public function save(MedicineCatalogService $catalog): void
    {
        $this->form->validate();
        $data = [...$this->form->normalize(), ...$this->form->billingData()];
        $this->form->id ? $catalog->updateMedicine(Medicine::query()->forCurrentFacility()->findOrFail($this->form->id), $data, auth()->user()) : $catalog->createMedicine($data, auth()->user());
        $this->showModal = false;
        Notifier::success('messages.saved');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBillingStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCatalogStatus(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $medicineId, MedicineCatalogService $catalog): void
    {
        $this->cancelDelete();

        try {
            $medicine = Medicine::query()->forCurrentFacility()->with('dispensingUnit')->findOrFail($medicineId);
            $this->deletionAssessment = $catalog->deletionAssessment($medicine, auth()->user());
            $this->deletingMedicineId = $medicine->id;
            $this->deletingMedicineName = trim($medicine->name.' '.($medicine->strength ?? ''));
            $this->deletingMedicineUnit = $medicine->dispensingUnit?->symbol
                ?? $medicine->dispensingUnit?->name
                ?? 'unit';
            $this->showDeleteModal = true;
        } catch (AuthorizationException) {
            $this->addError('delete', 'Huna ruhusa ya kufuta dawa.');
            Notifier::error('Huna ruhusa ya kufuta dawa.');
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('delete', 'Dawa haiwezi kufutwa kwa sasa. Tafadhali jaribu tena.');
            Notifier::error('Dawa haiwezi kufutwa kwa sasa. Tafadhali jaribu tena.');
        }
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingMedicineId = null;
        $this->deletingMedicineName = '';
        $this->deletingMedicineUnit = '';
        $this->deletionAssessment = [];
        $this->resetErrorBag('delete');
    }

    public function deleteMedicine(MedicineCatalogService $catalog): void
    {
        try {
            $medicine = Medicine::query()->forCurrentFacility()->findOrFail((int) $this->deletingMedicineId);
            $result = $catalog->removeMedicine($medicine, auth()->user());
            $this->cancelDelete();
            $this->resetPage();
            Notifier::success($result['outcome'] === 'deleted'
                ? 'Dawa imeondolewa.'
                : 'Dawa imeondolewa kwenye matumizi mapya. Historia yake imehifadhiwa.');
        } catch (AuthorizationException) {
            $this->cancelDelete();
            $this->addError('delete', 'Huna ruhusa ya kufuta dawa.');
            Notifier::error('Huna ruhusa ya kufuta dawa.');
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Dawa haiwezi kufutwa.';
            $this->cancelDelete();
            $this->addError('delete', $message);
            Notifier::warning($message);
        } catch (Throwable $exception) {
            report($exception);
            $this->cancelDelete();
            $this->addError('delete', 'Dawa haiwezi kufutwa kwa sasa. Tafadhali jaribu tena.');
            Notifier::error('Dawa haiwezi kufutwa kwa sasa. Tafadhali jaribu tena.');
        }
    }

    public function render(): View
    {
        $today = today()->toDateString();
        $medicines = Medicine::query()->forCurrentFacility()->with(['generic', 'category', 'dosageForm', 'dispensingUnit', 'service'])->withSum('batches', 'available_quantity')
            ->when($this->catalogStatus === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->catalogStatus === 'archived', fn ($q) => $q->where('is_active', false))
            ->when($this->search, fn ($q) => $q->where(fn ($search) => $search->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))
            ->when($this->billingStatus === 'ready', fn ($q) => $q->where('is_active', true)->whereHas('service', fn ($service) => $service->where('is_active', true)->where('service_type', 'medicine')->where(fn ($service) => $service->where('requires_payment', false)->orWhereHas('prices', fn ($price) => $this->applicableCashPrice($price, $today)))))
            ->when($this->billingStatus === 'missing_service', fn ($q) => $q->where(fn ($medicine) => $medicine->whereNull('service_id')->orWhereDoesntHave('service')))
            ->when($this->billingStatus === 'missing_price', fn ($q) => $q->whereHas('service', fn ($service) => $service->where('is_active', true)->where('requires_payment', true))->whereDoesntHave('service.prices', fn ($price) => $this->applicableCashPrice($price, $today)))
            ->when($this->billingStatus === 'needs_review', fn ($q) => $q->where(fn ($medicine) => $medicine
                ->where('is_active', false)
                ->orWhereHas('service', fn ($service) => $service->where('is_active', false)->orWhere('service_type', '!=', 'medicine'))
                ->orWhereHas('service', fn ($service) => $service->whereHas('prices', fn ($price) => $this->applicableCashPrice($price, $today), '>', 1))))
            ->paginate(12);
        $readiness = app(MedicineBillingReadinessService::class);
        $medicines->getCollection()->each(fn (Medicine $medicine) => $medicine->setAttribute('billing_readiness', $readiness->inspectForPayer($medicine, currentFacility()->id, PayerType::Cash)));
        $linkedServiceIds = Medicine::withTrashed()
            ->forCurrentFacility()
            ->whereNotNull('service_id')
            ->when($this->form->id, fn ($query) => $query->whereKeyNot($this->form->id))
            ->select('service_id');
        $services = Service::query()
            ->forCurrentFacility()
            ->where('service_type', 'medicine')
            ->whereNotIn('id', $linkedServiceIds)
            ->orderBy('name')
            ->get();

        return view('livewire.pharmacy.medicines.index', ['medicines' => $medicines, 'categories' => MedicineCategory::query()->forCurrentFacility()->get(), 'generics' => GenericMedicine::query()->forCurrentFacility()->get(), 'forms' => DosageForm::query()->forCurrentFacility()->get(), 'units' => MedicineUnit::query()->forCurrentFacility()->get(), 'routes' => MedicineRoute::query()->forCurrentFacility()->get(), 'services' => $services])->layout('components.layouts.app', ['title' => 'Medicines', 'description' => 'Medicine catalog, stock level na pricing link.']);
    }

    private function applicableCashPrice($query, string $today): void
    {
        $query->where('payer_type', 'cash')
            ->where('is_active', true)
            ->where(fn ($date) => $date->whereNull('effective_from')->orWhereDate('effective_from', '<=', $today))
            ->where(fn ($date) => $date->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today));
    }
}
