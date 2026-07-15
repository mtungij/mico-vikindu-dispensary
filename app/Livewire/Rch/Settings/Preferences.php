<?php

namespace App\Livewire\Rch\Settings;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Preferences extends Component
{
    public function mount(): void { Gate::authorize('rch.manage-settings'); }
    public function render(): View { return view('livewire.rch.settings.preferences')->layout('components.layouts.app', ['title'=>'RCH Preferences']); }
}
