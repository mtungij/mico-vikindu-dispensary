<?php

namespace App\Livewire\Laboratory\Specimens;

use App\Livewire\Forms\SpecimenTypeForm;
use App\Models\SpecimenType;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public SpecimenTypeForm $form; public bool $showModal = false; public ?SpecimenType $editing = null;
    public function mount(): void { Gate::authorize('laboratory.manage-specimens'); }
    public function create(): void { $this->editing = null; $this->form->resetForm(); $this->showModal = true; }
    public function edit(SpecimenType $specimen): void { abort_unless($specimen->facility_id === currentFacility()?->id, 404); $this->editing = $specimen; $this->form->fill($specimen->toArray()); $this->showModal = true; }
    public function save(): void { Gate::authorize('laboratory.manage-specimens'); $this->form->validate(); SpecimenType::query()->updateOrCreate(['id' => $this->editing?->id], [...$this->form->normalize(), 'facility_id' => currentFacility()->id, 'created_by' => $this->editing?->created_by ?? auth()->id(), 'updated_by' => auth()->id()]); $this->showModal = false; Notifier::success('messages.saved'); }
    public function render(): View { return view('livewire.laboratory.specimens.index', ['specimens' => SpecimenType::query()->forCurrentFacility()->orderBy('sort_order')->paginate(10)])->layout('components.layouts.app', ['title' => 'Specimen Types', 'description' => 'Sanidi aina za samples na containers.']); }
}
