<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class LaboratoryResultForm extends Form
{
    public array $values = []; public ?string $overall_result = null; public ?string $comments = null;
    public function rules(): array { return ['values' => ['array'], 'overall_result' => ['nullable', 'string'], 'comments' => ['nullable', 'string']]; }
    public function validationAttributes(): array { return ['values' => 'matokeo']; }
    public function normalize(): array { return ['overall_result' => $this->overall_result, 'comments' => $this->comments, ...$this->values ? ['values' => $this->values] : []]; }
    public function fillFromModel($model): void {}
    public function resetForm(): void { $this->reset(); }
}
