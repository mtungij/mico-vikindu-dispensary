<?php

namespace App\Livewire\Services;

use App\Enums\ServiceType;
use App\Livewire\Forms\ServiceForm;
use App\Models\Department;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public ServiceForm $form;
    public string $search = ''; public string $category = ''; public string $type = ''; public string $status = 'active'; public bool $showFormModal = false; public ?int $editingId = null;
    public function mount(): void { Gate::authorize('viewAny', Service::class); }
    public function create(): void { Gate::authorize('create', Service::class); $this->editingId = null; $this->form->resetForm(); $this->showFormModal = true; }
    public function edit(Service $service): void { Gate::authorize('update', $service); $this->editingId = $service->id; $this->form->resetForm(); $this->form->setModel($service); $this->showFormModal = true; }
    public function save(): void
    {
        $data = $this->form->data();
        if ($this->editingId) { $service = Service::query()->forCurrentFacility()->findOrFail($this->editingId); Gate::authorize('update', $service); $service->update([...$data, 'updated_by' => auth()->id()]); Notifier::success('messages.updated'); }
        else { Gate::authorize('create', Service::class); Service::query()->create([...$data, 'facility_id' => currentFacility()?->id, 'created_by' => auth()->id(), 'updated_by' => auth()->id()]); Notifier::success('messages.saved'); }
        $this->showFormModal = false;
    }
    public function toggle(Service $service): void { Gate::authorize('activate', $service); $service->update(['is_active' => ! $service->is_active, 'updated_by' => auth()->id()]); }
    public function delete(Service $service): void { Gate::authorize('delete', $service); $service->invoiceItems()->exists() ? $service->update(['is_active' => false]) : $service->delete(); }
    public function render(): View
    {
        $services = Service::query()->forCurrentFacility()->with(['category', 'department'])->with(['prices' => fn($q) => $q->where('payer_type','cash')->where('is_active', true)])
            ->when($this->search, fn($q) => $q->where(fn($q) => $q->where('name','like',"%{$this->search}%")->orWhere('code','like',"%{$this->search}%")))
            ->when($this->category, fn($q) => $q->where('service_category_id', $this->category))->when($this->type, fn($q) => $q->where('service_type', $this->type))
            ->when($this->status === 'active', fn($q) => $q->where('is_active', true))->when($this->status === 'inactive', fn($q) => $q->where('is_active', false))
            ->orderBy('sort_order')->orderBy('name')->paginate(10);
        return view('livewire.services.index', ['services' => $services, 'types' => ServiceType::cases(), 'categories' => ServiceCategory::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(), 'departments' => Department::query()->forCurrentFacility()->orderBy('name')->get()])
            ->layout('components.layouts.app', ['title' => 'Services', 'description' => 'Huduma, flags na bei zake.']);
    }
}
