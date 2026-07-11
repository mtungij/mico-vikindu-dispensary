<?php

namespace App\Livewire\Services\Categories;

use App\Enums\ServiceCategoryType;
use App\Livewire\Forms\ServiceCategoryForm;
use App\Models\Department;
use App\Models\ServiceCategory;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public ServiceCategoryForm $form;
    public string $search = ''; public string $type = ''; public string $status = 'active'; public bool $showFormModal = false; public ?int $editingId = null;
    public function mount(): void { Gate::authorize('viewAny', ServiceCategory::class); }
    public function create(): void { Gate::authorize('create', ServiceCategory::class); $this->editingId = null; $this->form->resetForm(); $this->showFormModal = true; }
    public function edit(ServiceCategory $category): void { Gate::authorize('update', $category); $this->editingId = $category->id; $this->form->resetForm(); $this->form->setModel($category); $this->showFormModal = true; }
    public function save(): void
    {
        $data = $this->form->data();
        if ($this->editingId) {
            $category = ServiceCategory::query()->forCurrentFacility()->findOrFail($this->editingId); Gate::authorize('update', $category); $category->update([...$data, 'updated_by' => auth()->id()]); Notifier::success('messages.updated');
        } else {
            Gate::authorize('create', ServiceCategory::class); ServiceCategory::query()->create([...$data, 'facility_id' => currentFacility()?->id, 'created_by' => auth()->id(), 'updated_by' => auth()->id()]); Notifier::success('messages.saved');
        }
        $this->showFormModal = false;
    }
    public function toggle(ServiceCategory $category): void { Gate::authorize('activate', $category); $category->update(['is_active' => ! $category->is_active, 'updated_by' => auth()->id()]); }
    public function delete(ServiceCategory $category): void { Gate::authorize('delete', $category); $category->services()->exists() ? $category->update(['is_active' => false]) : $category->delete(); Notifier::success('messages.deleted'); }
    public function render(): View
    {
        $categories = ServiceCategory::query()->forCurrentFacility()->with('department')->withCount('services')
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))
            ->when($this->type, fn ($q) => $q->where('category_type', $this->type))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('sort_order')->orderBy('name')->paginate(10);
        return view('livewire.services.categories.index', ['categories' => $categories, 'types' => ServiceCategoryType::cases(), 'departments' => Department::query()->forCurrentFacility()->orderBy('name')->get()])
            ->layout('components.layouts.app', ['title' => 'Service Categories', 'description' => 'Makundi ya huduma na departments zake.']);
    }
}
