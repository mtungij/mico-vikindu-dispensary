<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class PrescriptionItemForm extends Form
{
    public ?int $medicine_id = null;
    public string $medication_name = '';
    public ?string $generic_name = null;
    public ?string $strength = null;
    public ?string $dosage_form = null;
    public string $dose = '';
    public ?string $route = null;
    public string $frequency = '';
    public ?int $duration_value = null;
    public string $duration_unit = 'days';
    public ?string $quantity = null;
    public ?string $instructions = null;
    public ?string $indication = null;
    public bool $substitution_allowed = true;
    public function rules(): array { return ['medicine_id' => ['nullable', 'integer'], 'medication_name' => ['required_without:medicine_id', 'nullable', 'string', 'max:255'], 'dose' => ['required'], 'frequency' => ['required'], 'duration_value' => ['required', 'integer', 'min:1'], 'duration_unit' => ['required'], 'quantity' => ['nullable', 'numeric', 'min:0']]; }
    public function validationAttributes(): array { return ['medication_name' => 'jina la dawa']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->normalize()))); }
    public function resetForm(): void { $this->reset(); $this->duration_unit = 'days'; $this->substitution_allowed = true; }
}
