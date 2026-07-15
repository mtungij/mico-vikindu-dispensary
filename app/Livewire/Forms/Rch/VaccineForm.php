<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class VaccineForm extends Form
{
    public string $name = ''; public string $code = ''; public ?string $disease_prevented = null; public ?float $dosage = null; public ?string $dosage_unit = null; public bool $multi_dose_vial = false; public ?int $doses_per_vial = null; public ?int $service_id = null; public ?int $medicine_id = null; public bool $is_active = true;
    public function rules(): array { return ['name'=>'required|string|max:255','code'=>'required|string|max:60','disease_prevented'=>'nullable|string|max:255','dosage'=>'nullable|numeric|min:0','dosage_unit'=>'nullable|string|max:30','multi_dose_vial'=>'boolean','doses_per_vial'=>'nullable|integer|min:1','service_id'=>'nullable|exists:services,id','medicine_id'=>'nullable|exists:medicines,id','is_active'=>'boolean']; }
    public function validationAttributes(): array { return ['disease_prevented'=>'disease prevented']; }
    public function normalize(): array { $data = $this->validate(); $data['code'] = str($data['code'])->upper()->toString(); return $data; }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->is_active = true; }
}
