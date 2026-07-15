<?php

namespace App\Livewire\Rch\Settings;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ReportSettings extends Component
{
    public function mount(): void { Gate::authorize('rch.manage-settings'); }
    public function render(): View { return view('livewire.rch.settings.report-settings')->layout('components.layouts.app', ['title'=>'RCH Report Settings']); }
}
