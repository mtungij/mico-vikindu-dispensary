<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class LaboratoryOrderForm extends Form
{
    public array $service_ids = [];
    public string $priority = 'normal';
    public ?string $clinical_notes = null;
    public ?string $provisional_diagnosis = null;
    public function rules(): array { return ['service_ids' => ['required', 'array', 'min:1'], 'service_ids.*' => ['integer', 'distinct'], 'priority' => ['required', 'string'], 'clinical_notes' => ['nullable', 'string', 'max:2000'], 'provisional_diagnosis' => ['nullable', 'string', 'max:2000']]; }
    public function validationAttributes(): array { return ['service_ids' => 'vipimo']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->normalize()))); }
    public function resetForm(): void { $this->reset(); $this->priority = 'normal'; }
}
