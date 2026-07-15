<?php

namespace App\Livewire\Rch\Immunization;

use App\Services\ImmunizationScheduleService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Defaulters extends Component
{
    public function mount(): void { Gate::authorize('rch.immunization.view-defaulters'); }
    public function render(ImmunizationScheduleService $service): View { return view('livewire.rch.immunization.defaulters', ['children'=>$service->getDefaulters()])->layout('components.layouts.app', ['title'=>'Immunization Defaulters']); }
}
