<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DentalFindingForm extends Form
{
    public ?int $finding_type_id = null; public string $tooth_number = ''; public string $dentition_type = 'mixed'; public ?string $surface = null; public ?string $severity = null; public ?string $description = null;
    public function rules(): array { return ['finding_type_id'=>['required','integer'],'tooth_number'=>['required','string','max:8'],'dentition_type'=>['required','in:permanent,primary,mixed'],'surface'=>['nullable','string'],'severity'=>['nullable','string','max:50'],'description'=>['nullable','string','max:2000']]; }
    public function validationAttributes(): array { return ['tooth_number'=>'namba ya jino']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { $this->finding_type_id=$m->finding_type_id; $this->tooth_number=$m->toothRecord?->tooth_number ?? ''; $this->surface=$m->surface?->value ?? $m->surface; $this->severity=$m->severity; $this->description=$m->description; }
    public function resetForm(): void { $this->reset(); $this->dentition_type='mixed'; }
}
