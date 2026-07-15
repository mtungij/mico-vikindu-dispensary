<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class RchChildForm extends Form
{
    public ?int $child_patient_id = null; public ?int $mother_patient_id = null; public ?int $father_patient_id = null; public ?int $guardian_patient_id = null; public ?string $birth_date = null; public string $sex_at_birth = 'unknown'; public ?float $birth_weight_kg = null; public ?string $feeding_method = null; public ?string $notes = null;
    public function rules(): array { return ['child_patient_id'=>'required|exists:patients,id','mother_patient_id'=>'nullable|different:child_patient_id|exists:patients,id','father_patient_id'=>'nullable|different:child_patient_id|exists:patients,id','guardian_patient_id'=>'nullable|different:child_patient_id|exists:patients,id','birth_date'=>'required|date|before_or_equal:today','sex_at_birth'=>'required|string|max:20','birth_weight_kg'=>'nullable|numeric|min:0.2|max:8','feeding_method'=>'nullable|string|max:60','notes'=>'nullable|string']; }
    public function validationAttributes(): array { return ['child_patient_id'=>'child patient']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->sex_at_birth = 'unknown'; }
}
