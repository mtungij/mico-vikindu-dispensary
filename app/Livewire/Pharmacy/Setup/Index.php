<?php

namespace App\Livewire\Pharmacy\Setup;

use App\Livewire\Forms\DosageFormForm;
use App\Livewire\Forms\GenericMedicineForm;
use App\Livewire\Forms\MedicineCategoryForm;
use App\Livewire\Forms\MedicineRouteForm;
use App\Livewire\Forms\MedicineUnitForm;
use App\Livewire\Forms\StockLocationForm;
use App\Livewire\Forms\SupplierForm;
use App\Models\DosageForm;
use App\Models\GenericMedicine;
use App\Models\MedicineCategory;
use App\Models\MedicineRoute;
use App\Models\MedicineUnit;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public string $section = 'categories'; public bool $showModal = false; public string $search = '';
    public MedicineCategoryForm $categoryForm; public GenericMedicineForm $genericForm; public DosageFormForm $dosageForm; public MedicineUnitForm $unitForm; public MedicineRouteForm $routeForm; public SupplierForm $supplierForm; public StockLocationForm $locationForm;
    public function mount(string $section = 'categories'): void { $this->section = $section; Gate::authorize($this->permission()); }
    public function create(): void { Gate::authorize($this->permission()); $this->activeForm()->resetForm(); $this->showModal = true; }
    public function edit(int $id): void { $model = $this->model()::query()->forCurrentFacility()->findOrFail($id); $this->activeForm()->fillFromModel($model); $this->showModal = true; }
    public function save(): void
    {
        Gate::authorize($this->permission()); $form = $this->activeForm(); $form->validate(); $data = [...$form->normalize(), 'facility_id' => currentFacility()->id, 'updated_by' => auth()->id()];
        if ($form->id) $this->model()::query()->forCurrentFacility()->findOrFail($form->id)->update($data); else $this->model()::query()->create([...$data, 'created_by' => auth()->id()]);
        $this->showModal = false; Notifier::success('messages.saved');
    }
    public function render(): View { return view('livewire.pharmacy.setup.index', ['rows' => $this->rows(), 'section' => $this->section])->layout('components.layouts.app', ['title' => $this->title(), 'description' => 'Mipangilio ya pharmacy catalog na stock.']); }
    private function rows() { return $this->model()::query()->forCurrentFacility()->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"))->latest()->paginate(10); }
    private function model(): string { return match ($this->section) { 'generics' => GenericMedicine::class, 'dosage-forms' => DosageForm::class, 'units' => MedicineUnit::class, 'routes' => MedicineRoute::class, 'suppliers' => Supplier::class, 'stock-locations' => StockLocation::class, default => MedicineCategory::class }; }
    private function activeForm(): object { return match ($this->section) { 'generics' => $this->genericForm, 'dosage-forms' => $this->dosageForm, 'units' => $this->unitForm, 'routes' => $this->routeForm, 'suppliers' => $this->supplierForm, 'stock-locations' => $this->locationForm, default => $this->categoryForm }; }
    private function permission(): string { return match ($this->section) { 'generics' => 'pharmacy.manage-generics', 'dosage-forms' => 'pharmacy.manage-dosage-forms', 'units' => 'pharmacy.manage-units', 'routes' => 'pharmacy.manage-routes', 'suppliers' => 'pharmacy.manage-suppliers', 'stock-locations' => 'pharmacy.manage-stock-locations', default => 'pharmacy.manage-medicine-categories' }; }
    private function title(): string { return match ($this->section) { 'generics' => 'Generic Medicines', 'dosage-forms' => 'Dosage Forms', 'units' => 'Medicine Units', 'routes' => 'Routes of Administration', 'suppliers' => 'Suppliers', 'stock-locations' => 'Stock Locations', default => 'Medicine Categories' }; }
}
