<?php

namespace App\Livewire\Pharmacy\Medicines;

use App\Livewire\Forms\MedicineForm;
use App\Models\DosageForm;
use App\Models\GenericMedicine;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineRoute;
use App\Models\MedicineUnit;
use App\Models\Service;
use App\Services\MedicineCatalogService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public MedicineForm $form; public bool $showModal = false; public string $search = '';
    public function mount(): void { Gate::authorize('pharmacy.manage-medicines'); }
    public function create(): void { $this->form->resetForm(); $this->showModal = true; }
    public function edit(Medicine $medicine): void { abort_unless($medicine->facility_id === currentFacility()?->id, 404); $this->form->fillFromModel($medicine); $this->showModal = true; }
    public function save(MedicineCatalogService $catalog): void { $this->form->validate(); $this->form->id ? Medicine::query()->forCurrentFacility()->findOrFail($this->form->id)->update([...$this->form->normalize(), 'updated_by' => auth()->id()]) : $catalog->createMedicine($this->form->normalize(), auth()->user()); $this->showModal = false; Notifier::success('messages.saved'); }
    public function render(): View
    {
        return view('livewire.pharmacy.medicines.index', ['medicines' => Medicine::query()->forCurrentFacility()->with(['generic', 'category', 'dosageForm', 'dispensingUnit'])->withSum('batches', 'available_quantity')->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"))->paginate(12), 'categories' => MedicineCategory::query()->forCurrentFacility()->get(), 'generics' => GenericMedicine::query()->forCurrentFacility()->get(), 'forms' => DosageForm::query()->forCurrentFacility()->get(), 'units' => MedicineUnit::query()->forCurrentFacility()->get(), 'routes' => MedicineRoute::query()->forCurrentFacility()->get(), 'services' => Service::query()->forCurrentFacility()->where('service_type', 'medicine')->get()])->layout('components.layouts.app', ['title' => 'Medicines', 'description' => 'Medicine catalog, stock level na pricing link.']);
    }
}
