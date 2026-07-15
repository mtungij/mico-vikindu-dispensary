<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class FamilyPlanningMethodForm extends Form
{
    public string $name = ''; public string $code = ''; public string $category = 'short_acting'; public ?int $duration_days = null; public bool $requires_procedure = false; public bool $requires_prescription = false; public bool $requires_inventory_item = false; public ?int $medicine_id = null; public ?int $service_id = null; public bool $is_active = true;
    public function rules(): array { return ['name'=>'required|string|max:255','code'=>'required|string|max:60','category'=>'required|string|max:80','duration_days'=>'nullable|integer|min:1','requires_procedure'=>'boolean','requires_prescription'=>'boolean','requires_inventory_item'=>'boolean','medicine_id'=>'nullable|exists:medicines,id','service_id'=>'nullable|exists:services,id','is_active'=>'boolean']; }
    public function validationAttributes(): array { return ['duration_days'=>'duration']; }
    public function normalize(): array { $data = $this->validate(); $data['code'] = str($data['code'])->upper()->toString(); return $data; }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->category = 'short_acting'; $this->is_active = true; }
}
