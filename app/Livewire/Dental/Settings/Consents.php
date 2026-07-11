<?php

namespace App\Livewire\Dental\Settings;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Consents extends Component
{
    public function mount(): void { Gate::authorize('dental.manage-consents'); }
    public function render(): View { return view('livewire.dental.settings.consents')->layout('components.layouts.app',['title'=>'Dental Consent Templates','description'=>'Foundation ya consent templates.']); }
}
