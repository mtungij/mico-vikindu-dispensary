<?php

namespace App\Livewire\Dental\Settings;

use App\Livewire\Forms\DentalMaterialForm;
use App\Models\DentalMaterial;
use App\Services\DentalMaterialService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Materials extends Component
{
    public DentalMaterialForm $form; public bool $showModal=false;
    public function mount(): void { Gate::authorize('dental.manage-materials'); }
    public function create(): void { $this->form->resetForm(); $this->showModal=true; }
    public function save(DentalMaterialService $service): void { Gate::authorize('dental.manage-materials'); $service->save($this->form->normalize(), auth()->user()); $this->showModal=false; Notifier::success('messages.saved'); }
    public function render(): View { return view('livewire.dental.settings.materials', ['rows'=>DentalMaterial::query()->forCurrentFacility()->paginate(12)])->layout('components.layouts.app',['title'=>'Dental Materials','description'=>'Vifaa/materials za dental procedures.']); }
}
