<?php

namespace App\Livewire\Dental\Settings;

use App\Models\FacilitySetting;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Preferences extends Component
{
    public bool $dental_require_payment_before_consultation=true; public bool $dental_require_payment_before_procedure=true; public bool $dental_allow_emergency_override=true; public bool $dental_bill_materials_separately=false; public bool $dental_require_consent_for_surgery=true; public bool $dental_require_signature_for_report=false; public string $dental_default_numbering_system='fdi'; public bool $dental_enable_periodontal_charting=true; public bool $dental_enable_mixed_dentition=true; public bool $dental_enable_chair_assignment=true; public bool $dental_auto_create_follow_up=false; public string $dental_attachment_max_mb='10';
    public function mount(): void { Gate::authorize('dental.manage-settings'); foreach (array_keys($this->settings()) as $key) { $value=FacilitySetting::query()->where('facility_id',currentFacility()?->id)->where('key',$key)->value('value'); if ($value !== null) $this->{$key}=is_bool($this->{$key}) ? filter_var($value,FILTER_VALIDATE_BOOLEAN) : (string) $value; } }
    public function save(): void { foreach ($this->settings() as $key=>$type) FacilitySetting::query()->updateOrCreate(['facility_id'=>currentFacility()->id,'key'=>$key], ['value'=>is_bool($this->{$key}) ? ($this->{$key}?'1':'0') : $this->{$key},'type'=>$type,'group'=>'dental','is_public'=>false]); Notifier::success('messages.saved'); }
    public function render(): View { return view('livewire.dental.settings.preferences')->layout('components.layouts.app',['title'=>'Dental Preferences','description'=>'Mipangilio ya dental workflow.']); }
    private function settings(): array { return ['dental_require_payment_before_consultation'=>'boolean','dental_require_payment_before_procedure'=>'boolean','dental_allow_emergency_override'=>'boolean','dental_bill_materials_separately'=>'boolean','dental_require_consent_for_surgery'=>'boolean','dental_require_signature_for_report'=>'boolean','dental_default_numbering_system'=>'string','dental_enable_periodontal_charting'=>'boolean','dental_enable_mixed_dentition'=>'boolean','dental_enable_chair_assignment'=>'boolean','dental_auto_create_follow_up'=>'boolean','dental_attachment_max_mb'=>'integer']; }
}
