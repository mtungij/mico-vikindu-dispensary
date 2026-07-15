<?php

namespace App\Livewire\Rch\Settings;

use App\Models\ImmunizationSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ImmunizationSchedules extends Component
{
    public function mount(): void { Gate::authorize('rch.immunization.manage-schedules'); }
    public function render(): View { return view('livewire.rch.settings.immunization-schedules', ['items'=>ImmunizationSchedule::query()->forCurrentFacility()->with('items.vaccine')->get()])->layout('components.layouts.app', ['title'=>'Immunization Schedules']); }
}
