<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class ObstetricHistoryForm extends Form
{
    public ?int $pregnancy_year = null; public string $outcome = 'unknown'; public ?int $gestational_age_weeks = null; public ?string $delivery_mode = null; public ?string $child_sex = null; public ?float $birth_weight_kg = null; public ?string $notes = null;
    public function rules(): array { return ['pregnancy_year'=>'nullable|integer|min:1950|max:2100','outcome'=>'required|string|max:40','gestational_age_weeks'=>'nullable|integer|min:0|max:45','delivery_mode'=>'nullable|string|max:80','child_sex'=>'nullable|string|max:20','birth_weight_kg'=>'nullable|numeric|min:0.2|max:8','notes'=>'nullable|string']; }
    public function validationAttributes(): array { return ['pregnancy_year'=>'pregnancy year']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->outcome = 'unknown'; }
}
