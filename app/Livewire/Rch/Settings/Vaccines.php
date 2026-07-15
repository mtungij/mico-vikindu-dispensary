<?php

namespace App\Livewire\Rch\Settings;

use App\Models\Vaccine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Vaccines extends Component
{
    public function mount(): void { Gate::authorize('rch.vaccines.manage'); }
    public function render(): View { return view('livewire.rch.settings.vaccines', ['items'=>Vaccine::query()->where(fn($q)=>$q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id))->orderBy('sort_order')->get()])->layout('components.layouts.app', ['title'=>'Vaccines']); }
}
