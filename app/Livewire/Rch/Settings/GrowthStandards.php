<?php

namespace App\Livewire\Rch\Settings;

use App\Models\GrowthReferenceStandard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class GrowthStandards extends Component
{
    public function mount(): void { Gate::authorize('rch.manage-settings'); }
    public function render(): View { return view('livewire.rch.settings.growth-standards', ['count'=>GrowthReferenceStandard::query()->count(), 'standards'=>GrowthReferenceStandard::query()->select('standard_name')->distinct()->pluck('standard_name')])->layout('components.layouts.app', ['title'=>'Growth Standards']); }
}
