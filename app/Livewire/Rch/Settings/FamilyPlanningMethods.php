<?php

namespace App\Livewire\Rch\Settings;

use App\Models\FamilyPlanningMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class FamilyPlanningMethods extends Component
{
    public function mount(): void { Gate::authorize('rch.family-planning.manage-methods'); }
    public function render(): View { return view('livewire.rch.settings.family-planning-methods', ['items'=>FamilyPlanningMethod::query()->where(fn($q)=>$q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id))->orderBy('sort_order')->get()])->layout('components.layouts.app', ['title'=>'Family Planning Methods']); }
}
