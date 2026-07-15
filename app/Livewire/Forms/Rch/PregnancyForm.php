<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class PregnancyForm extends Form
{
    public ?int $patient_id = null; public ?string $lmp_date = null; public bool $lmp_is_certain = true; public ?int $gravida = null; public ?int $para = null; public ?float $booking_weight_kg = null; public ?float $booking_height_cm = null; public bool $multiple_pregnancy = false; public ?int $number_of_fetuses = null; public ?string $notes = null; public bool $override_duplicate = false; public ?string $override_reason = null;
    public function rules(): array { return ['patient_id'=>'required|exists:patients,id','lmp_date'=>'nullable|date|before_or_equal:today','lmp_is_certain'=>'boolean','gravida'=>'nullable|integer|min:0','para'=>'nullable|integer|min:0','booking_weight_kg'=>'nullable|numeric|min:20|max:250','booking_height_cm'=>'nullable|numeric|min:80|max:230','multiple_pregnancy'=>'boolean','number_of_fetuses'=>'nullable|integer|min:1|max:8','notes'=>'nullable|string','override_duplicate'=>'boolean','override_reason'=>'nullable|string|max:500']; }
    public function validationAttributes(): array { return ['patient_id'=>'patient','lmp_date'=>'LMP date']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->lmp_is_certain = true; }
}
