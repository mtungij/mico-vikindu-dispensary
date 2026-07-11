<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DiagnosisForm extends Form
{
    public string $diagnosis_type = 'provisional';
    public ?string $icd10_code = null;
    public string $diagnosis_name = '';
    public ?string $description = null;
    public string $certainty = 'suspected';
    public bool $is_primary = false;
    public function rules(): array { return ['diagnosis_type' => ['required', 'in:provisional,differential,final,confirmed,rule_out'], 'icd10_code' => ['nullable', 'string', 'max:20'], 'diagnosis_name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'certainty' => ['required', 'in:suspected,probable,confirmed'], 'is_primary' => ['boolean']]; }
    public function validationAttributes(): array { return ['diagnosis_name' => 'diagnosis']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->normalize()))); }
    public function resetForm(): void { $this->reset(); $this->diagnosis_type = 'provisional'; $this->certainty = 'suspected'; }
}
