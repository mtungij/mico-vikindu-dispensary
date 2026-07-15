<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class ChildGrowthMeasurementForm extends Form
{
    public ?string $measured_at = null; public ?float $weight_kg = null; public ?float $length_height_cm = null; public ?float $head_circumference_cm = null; public ?float $muac_cm = null; public bool $edema_present = false; public ?string $feeding_method = null; public ?string $notes = null;
    public function rules(): array { return ['measured_at'=>'required|date','weight_kg'=>'nullable|numeric|min:0.5|max:80','length_height_cm'=>'nullable|numeric|min:20|max:220','head_circumference_cm'=>'nullable|numeric|min:20|max:80','muac_cm'=>'nullable|numeric|min:5|max:40','edema_present'=>'boolean','feeding_method'=>'nullable|string|max:60','notes'=>'nullable|string']; }
    public function validationAttributes(): array { return ['length_height_cm'=>'length/height']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); }
}
