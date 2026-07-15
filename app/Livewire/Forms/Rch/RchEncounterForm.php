<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class RchEncounterForm extends Form
{
    public string $encounter_type = 'rch_general'; public ?string $chief_complaint = null; public ?string $clinical_summary = null; public ?string $treatment_plan = null; public bool $follow_up_required = false; public ?string $follow_up_date = null;
    public function rules(): array { return ['encounter_type'=>'required|string|max:40','chief_complaint'=>'nullable|string','clinical_summary'=>'nullable|string','treatment_plan'=>'nullable|string','follow_up_required'=>'boolean','follow_up_date'=>'nullable|date|after_or_equal:today']; }
    public function validationAttributes(): array { return ['encounter_type'=>'encounter type','follow_up_date'=>'follow-up date']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->encounter_type = 'rch_general'; }
}
