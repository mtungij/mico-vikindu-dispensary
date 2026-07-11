<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DentalProcedureForm extends Form
{
    public ?int $service_id = null; public string $procedure_type = 'preventive'; public ?string $tooth_number = null; public array $surfaces = []; public ?string $indication = null; public ?string $diagnosis_snapshot = null; public ?string $anaesthesia_type = null; public ?string $anaesthetic_used = null; public ?string $anaesthetic_quantity = null; public ?string $findings = null; public ?string $technique_notes = null; public ?string $post_procedure_instructions = null; public bool $follow_up_required = false; public ?string $follow_up_date = null;
    public function rules(): array { return ['service_id'=>['required','integer'],'procedure_type'=>['required','string'],'tooth_number'=>['nullable','string','max:8'],'surfaces'=>['array'],'indication'=>['nullable','string'],'diagnosis_snapshot'=>['nullable','string'],'anaesthesia_type'=>['nullable','string'],'anaesthetic_used'=>['nullable','string'],'anaesthetic_quantity'=>['nullable','string'],'findings'=>['nullable','string'],'technique_notes'=>['nullable','string'],'post_procedure_instructions'=>['nullable','string'],'follow_up_required'=>['boolean'],'follow_up_date'=>['nullable','date']]; }
    public function validationAttributes(): array { return []; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { foreach (array_keys($this->rules()) as $k) if (isset($m->{$k})) $this->{$k}=$m->{$k}; }
    public function resetForm(): void { $this->reset(); $this->procedure_type='preventive'; $this->surfaces=[]; }
}
