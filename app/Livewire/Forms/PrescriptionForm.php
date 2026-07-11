<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class PrescriptionForm extends Form
{
    public ?string $notes = null;
    public array $items = [];
    public function rules(): array { return ['notes' => ['nullable', 'string', 'max:2000'], 'items' => ['array']]; }
    public function validationAttributes(): array { return ['items' => 'dawa']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->normalize()))); }
    public function resetForm(): void { $this->reset(); }
}
