<?php

namespace App\Livewire\Observation\Settings;

use App\Livewire\Forms\BedForm;
use App\Models\Bed;
use App\Models\ObservationRoom;
use App\Services\BedManagementService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Beds extends Component { use WithPagination; public BedForm $form; public bool $showModal=false; public function mount(): void { Gate::authorize('observation.manage-beds'); } public function create(): void { $this->form->resetForm(); $this->showModal=true; } public function edit(int $id): void { $this->form->fillFromModel(Bed::query()->forCurrentFacility()->findOrFail($id)); $this->showModal=true; } public function save(BedManagementService $service): void { $this->form->validate(); $bed=$this->form->id ? Bed::query()->forCurrentFacility()->findOrFail($this->form->id) : null; $service->save($this->form->normalize(), auth()->user(), $bed); $this->showModal=false; Notifier::success('observation.saved'); } public function render(): View { return view('livewire.observation.settings.beds', ['beds'=>Bed::query()->forCurrentFacility()->with('room')->paginate(12), 'rooms'=>ObservationRoom::query()->forCurrentFacility()->get()])->layout('components.layouts.app', ['title'=>'Observation Beds','description'=>'Mipangilio ya vitanda na rates za fallback.']); } }
