<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class ProcedureOrderForm extends Form
{
    public ?int $service_id = null;
    public string $procedure_name_snapshot = '';
    public ?string $instructions = null;
    public string $priority = 'normal';
    public ?string $scheduled_at = null;
    public ?string $notes = null;
    public function rules(): array { return ['service_id' => ['nullable', 'integer'], 'procedure_name_snapshot' => ['required_without:service_id', 'string', 'max:255'], 'instructions' => ['nullable', 'string'], 'priority' => ['required'], 'scheduled_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string']]; }
    public function validationAttributes(): array { return ['procedure_name_snapshot' => 'procedure']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->normalize()))); }
    public function resetForm(): void { $this->reset(); $this->priority = 'normal'; }
}
