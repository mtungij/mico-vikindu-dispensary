<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class PregnancyDatingForm extends Form
{
    public string $dating_method = 'lmp'; public ?string $reference_date = null; public ?int $gestational_age_weeks = null; public ?int $gestational_age_days = null; public ?string $reason = null;
    public function rules(): array { return ['dating_method'=>'required|in:lmp,ultrasound,clinical_estimate,embryo_transfer,unknown','reference_date'=>'required|date','gestational_age_weeks'=>'nullable|integer|min:0|max:45','gestational_age_days'=>'nullable|integer|min:0|max:6','reason'=>'nullable|string']; }
    public function validationAttributes(): array { return ['reference_date'=>'reference date']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->dating_method = 'lmp'; }
}
