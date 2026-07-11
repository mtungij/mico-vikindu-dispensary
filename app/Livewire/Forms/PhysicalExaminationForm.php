<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class PhysicalExaminationForm extends Form
{
    public string $examination_system = 'general';
    public ?string $findings = null;
    public ?string $status = 'normal';
    public function rules(): array { return ['examination_system' => ['required'], 'findings' => ['nullable', 'string'], 'status' => ['nullable', 'in:normal,abnormal,not_examined']]; }
    public function validationAttributes(): array { return ['examination_system' => 'system']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->normalize()))); }
    public function resetForm(): void { $this->reset(); $this->examination_system = 'general'; $this->status = 'normal'; }
}
