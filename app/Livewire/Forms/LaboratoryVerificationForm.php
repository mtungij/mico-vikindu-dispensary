<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class LaboratoryVerificationForm extends Form
{
    public ?string $comment = null; public ?string $return_reason = null;
    public function rules(): array { return ['comment' => ['nullable', 'string'], 'return_reason' => ['nullable', 'string']]; }
    public function validationAttributes(): array { return ['return_reason' => 'sababu']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void {}
    public function resetForm(): void { $this->reset(); }
}
