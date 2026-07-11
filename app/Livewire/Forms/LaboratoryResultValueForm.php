<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class LaboratoryResultValueForm extends Form
{
    public ?string $value = null; public ?string $comments = null;
    public function rules(): array { return ['value' => ['nullable'], 'comments' => ['nullable', 'string']]; }
    public function validationAttributes(): array { return ['value' => 'result']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void {}
    public function resetForm(): void { $this->reset(); }
}
