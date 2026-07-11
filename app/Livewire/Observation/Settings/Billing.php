<?php

namespace App\Livewire\Observation\Settings;

use App\Models\FacilitySetting;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Billing extends Component { public string $mode='hourly'; public string $rounding='round_up_hour'; public function mount(): void { Gate::authorize('observation.manage-billing'); $this->mode=FacilitySetting::query()->where('facility_id',currentFacility()->id)->where('key','observation_billing_mode')->value('value') ?? 'hourly'; $this->rounding=FacilitySetting::query()->where('facility_id',currentFacility()->id)->where('key','observation_hour_rounding')->value('value') ?? 'round_up_hour'; } public function save(): void { foreach (['observation_billing_mode'=>$this->mode,'observation_hour_rounding'=>$this->rounding] as $key=>$value) FacilitySetting::query()->updateOrCreate(['facility_id'=>currentFacility()->id,'key'=>$key], ['value'=>$value]); Notifier::success('observation.saved'); } public function render(): View { return view('livewire.observation.settings.billing')->layout('components.layouts.app', ['title'=>'Observation Billing','description'=>'Mipangilio ya billing mode na rounding.']); } }
