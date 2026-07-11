<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class OrthodonticCaseForm extends Form
{
    public string $chief_concern = ''; public ?string $diagnosis = null; public ?string $malocclusion_class = null; public ?string $treatment_goal = null; public ?string $appliance_type = null; public ?string $treatment_start_date = null; public ?int $expected_duration_months = null; public string $status = 'assessment'; public ?string $notes = null;
    public function rules(): array { return ['chief_concern'=>['required','string'],'diagnosis'=>['nullable','string'],'malocclusion_class'=>['nullable','string'],'treatment_goal'=>['nullable','string'],'appliance_type'=>['nullable','string'],'treatment_start_date'=>['nullable','date'],'expected_duration_months'=>['nullable','integer','min:1'],'status'=>['required','string'],'notes'=>['nullable','string']]; }
    public function validationAttributes(): array { return []; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { foreach (array_keys($this->rules()) as $k) $this->{$k}=$m->{$k}; }
    public function resetForm(): void { $this->reset(); $this->status='assessment'; }
}
