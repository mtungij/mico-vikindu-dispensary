<?php

namespace App\Livewire\Dental\Settings;

use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Services extends Component
{
    public function mount(): void { Gate::authorize('services.view'); }
    public function render(): View { return view('livewire.dental.settings.services', ['rows'=>Service::query()->forCurrentFacility()->whereIn('service_type',['dental_service','consultation','procedure'])->whereHas('department', fn($q)=>$q->where('code','DEN'))->with('prices')->paginate(12)])->layout('components.layouts.app',['title'=>'Dental Services','description'=>'Huduma na bei za dental.']); }
}
