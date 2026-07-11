<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DentalLabOrderForm extends Form
{
    public string $work_type = 'crown'; public array $tooth_numbers = []; public ?string $shade = null; public ?string $material = null; public ?string $design_instructions = null; public ?string $external_lab_name = null; public ?string $external_reference = null; public ?string $expected_at = null; public string $status = 'draft';
    public function rules(): array { return ['work_type'=>['required','string'],'tooth_numbers'=>['array'],'shade'=>['nullable','string'],'material'=>['nullable','string'],'design_instructions'=>['nullable','string'],'external_lab_name'=>['nullable','string'],'external_reference'=>['nullable','string'],'expected_at'=>['nullable','date'],'status'=>['required','string']]; }
    public function validationAttributes(): array { return []; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { foreach (array_keys($this->rules()) as $k) $this->{$k}=$m->{$k}; }
    public function resetForm(): void { $this->reset(); $this->work_type='crown'; $this->status='draft'; }
}
