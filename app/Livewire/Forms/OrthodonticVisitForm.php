<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class OrthodonticVisitForm extends Form
{
    public ?int $dental_encounter_id = null; public ?string $visit_date = null; public string $visit_type = 'review'; public ?string $procedure_done = null; public ?string $appliance_status = null; public ?string $next_visit_date = null; public ?string $notes = null;
    public function rules(): array { return ['dental_encounter_id'=>['nullable','integer'],'visit_date'=>['required','date'],'visit_type'=>['required','string'],'procedure_done'=>['nullable','string'],'appliance_status'=>['nullable','string'],'next_visit_date'=>['nullable','date'],'notes'=>['nullable','string']]; }
    public function validationAttributes(): array { return []; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { foreach (array_keys($this->rules()) as $k) $this->{$k}=$m->{$k}; }
    public function resetForm(): void { $this->reset(); $this->visit_type='review'; }
}
