<?php

namespace App\Livewire\Laboratory\Tests;

use App\Livewire\Forms\LaboratoryTestForm;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestCategory;
use App\Models\Service;
use App\Models\SpecimenType;
use App\Services\LaboratoryTestService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public LaboratoryTestForm $form; public bool $showModal = false; public ?LaboratoryTest $editing = null; public string $search = '';
    public function mount(): void { Gate::authorize('laboratory.manage-tests'); }
    public function create(): void { $this->editing = null; $this->form->resetForm(); $this->showModal = true; }
    public function edit(LaboratoryTest $laboratoryTest): void { abort_unless($laboratoryTest->facility_id === currentFacility()?->id, 404); $this->editing = $laboratoryTest; $this->form->fillFromModel($laboratoryTest); $this->showModal = true; }
    public function save(LaboratoryTestService $service): void
    {
        Gate::authorize('laboratory.manage-tests'); $this->form->validate();
        if ($this->editing) { $this->editing->update([...$this->form->normalize(), 'updated_by' => auth()->id()]); } else { $service->createTest($this->form->normalize(), auth()->user()); }
        $this->showModal = false; Notifier::success('messages.saved');
    }
    public function render(): View
    {
        return view('livewire.laboratory.tests.index', [
            'tests' => LaboratoryTest::query()->forCurrentFacility()->with(['category','specimenType','service'])->withCount('parameters')->when($this->search, fn($q) => $q->where('name','like',"%{$this->search}%")->orWhere('code','like',"%{$this->search}%"))->paginate(10),
            'categories' => LaboratoryTestCategory::query()->forCurrentFacility()->where('is_active', true)->get(),
            'specimens' => SpecimenType::query()->forCurrentFacility()->where('is_active', true)->get(),
            'services' => Service::query()->forCurrentFacility()->where('service_type', 'laboratory_test')->where('is_active', true)->get(),
        ])->layout('components.layouts.app', ['title' => 'Laboratory Tests', 'description' => 'Sanidi vipimo, services, result types na turnaround time.']);
    }
}
