<?php

namespace App\Livewire\Observation\Settings;

use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Services extends Component { public function mount(): void { Gate::authorize('services.view'); } public function render(): View { return view('livewire.observation.settings.services', ['services'=>Service::query()->forCurrentFacility()->whereHas('category', fn($q)=>$q->where('category_type','bed_rest'))->with('prices')->paginate(12)])->layout('components.layouts.app', ['title'=>'Observation Services','description'=>'Observation billing hutumia Service Catalog na Service Prices zilizopo.']); } }
