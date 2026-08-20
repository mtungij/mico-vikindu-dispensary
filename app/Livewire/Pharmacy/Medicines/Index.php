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
use App\Services\MedicineCatalogService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public MedicineForm $form;

    public bool $showModal = false;

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('pharmacy.manage-medicines');
    }

    public function create(): void
    {
        $this->form->resetForm();
        $this->showModal = true;
    }

    public function edit(Medicine $medicine): void
    {
        abort_unless($medicine->facility_id === currentFacility()?->id, 404);
        $this->form->fillFromModel($medicine);
        $this->showModal = true;
    }

    public function save(MedicineCatalogService $catalog): void
    {
        $this->form->validate();
        $this->form->id ? $catalog->updateMedicine(Medicine::query()->forCurrentFacility()->findOrFail($this->form->id), $this->form->normalize(), auth()->user()) : $catalog->createMedicine($this->form->normalize(), auth()->user());
        $this->showModal = false;
        Notifier::success('messages.saved');
    }

    public function render(): View
    {
        $medicines = Medicine::query()->forCurrentFacility()->with(['generic', 'category', 'dosageForm', 'dispensingUnit', 'service'])->withSum('batches', 'available_quantity')->when($this->search, fn ($q) => $q->where(fn ($search) => $search->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))->paginate(12);
        $readiness = app(MedicineBillingReadinessService::class);
        $medicines->getCollection()->each(fn (Medicine $medicine) => $medicine->setAttribute('billing_readiness', $readiness->inspectForPayer($medicine, currentFacility()->id, PayerType::Cash)));

        return view('livewire.pharmacy.medicines.index', ['medicines' => $medicines, 'categories' => MedicineCategory::query()->forCurrentFacility()->get(), 'generics' => GenericMedicine::query()->forCurrentFacility()->get(), 'forms' => DosageForm::query()->forCurrentFacility()->get(), 'units' => MedicineUnit::query()->forCurrentFacility()->get(), 'routes' => MedicineRoute::query()->forCurrentFacility()->get(), 'services' => Service::query()->forCurrentFacility()->where('service_type', 'medicine')->orderBy('name')->get()])->layout('components.layouts.app', ['title' => 'Medicines', 'description' => 'Medicine catalog, stock level na pricing link.']);
    }
}
