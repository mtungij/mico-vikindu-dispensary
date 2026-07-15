<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class PregnancyRiskFactorForm extends Form
{
    public ?int $risk_factor_type_id = null; public string $severity = 'moderate'; public ?string $details = null;
    public function rules(): array { return ['risk_factor_type_id'=>'required|exists:pregnancy_risk_factor_types,id','severity'=>'required|in:low,moderate,high,critical','details'=>'nullable|string']; }
    public function validationAttributes(): array { return ['risk_factor_type_id'=>'risk factor']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->severity = 'moderate'; }
}
