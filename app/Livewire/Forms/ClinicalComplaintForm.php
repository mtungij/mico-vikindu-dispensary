<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class ClinicalComplaintForm extends Form
{
    public string $complaint = '';
    public ?int $duration_value = null;
    public ?string $duration_unit = null;
    public ?string $severity = null;
    public ?string $notes = null;
    public bool $is_primary = false;
    public function rules(): array { return ['complaint' => ['required', 'string', 'max:255'], 'duration_value' => ['nullable', 'integer', 'min:1'], 'duration_unit' => ['nullable', 'in:hours,days,weeks,months,years'], 'severity' => ['nullable', 'in:mild,moderate,severe'], 'notes' => ['nullable', 'string', 'max:2000'], 'is_primary' => ['boolean']]; }
    public function validationAttributes(): array { return ['complaint' => 'complaint']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->normalize()))); }
    public function resetForm(): void { $this->reset(); }
}
