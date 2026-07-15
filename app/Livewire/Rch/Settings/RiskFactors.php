<?php

namespace App\Livewire\Rch\Settings;

use App\Models\PregnancyRiskFactorType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class RiskFactors extends Component
{
    public function mount(): void { Gate::authorize('rch.manage-settings'); }
    public function render(): View { return view('livewire.rch.settings.risk-factors', ['items'=>PregnancyRiskFactorType::query()->where(fn($q)=>$q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id))->orderBy('sort_order')->get()])->layout('components.layouts.app', ['title'=>'Pregnancy Risk Factors']); }
}
