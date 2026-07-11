<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class LaboratorySampleRejectionForm extends Form
{
    public ?int $rejection_reason_id = null; public string $rejection_notes = '';
    public function rules(): array { return ['rejection_reason_id' => ['required', 'integer'], 'rejection_notes' => ['required', 'string']]; }
    public function validationAttributes(): array { return ['rejection_reason_id' => 'sababu']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void {}
    public function resetForm(): void { $this->reset(); }
}
