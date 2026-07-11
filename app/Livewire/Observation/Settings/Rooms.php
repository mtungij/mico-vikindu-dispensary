<?php

namespace App\Livewire\Observation\Settings;

use App\Livewire\Forms\ObservationRoomForm;
use App\Models\Department;
use App\Models\ObservationRoom;
use App\Services\ObservationRoomService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Rooms extends Component { use WithPagination; public ObservationRoomForm $form; public bool $showModal=false; public function mount(): void { Gate::authorize('observation.manage-rooms'); } public function create(): void { $this->form->resetForm(); $this->showModal=true; } public function edit(int $id): void { $this->form->fillFromModel(ObservationRoom::query()->forCurrentFacility()->findOrFail($id)); $this->showModal=true; } public function save(ObservationRoomService $service): void { $this->form->validate(); $room=$this->form->id ? ObservationRoom::query()->forCurrentFacility()->findOrFail($this->form->id) : null; $service->save($this->form->normalize(), auth()->user(), $room); $this->showModal=false; Notifier::success('observation.saved'); } public function render(): View { return view('livewire.observation.settings.rooms', ['rooms'=>ObservationRoom::query()->forCurrentFacility()->withCount('beds')->paginate(12), 'departments'=>Department::query()->forCurrentFacility()->get()])->layout('components.layouts.app', ['title'=>'Observation Rooms','description'=>'Mipangilio ya vyumba vya Bed Rest / Uangalizi.']); } }
