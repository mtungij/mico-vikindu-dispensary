<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class ImmunizationScheduleForm extends Form
{
    public string $name = ''; public string $code = ''; public string $target_group = 'child'; public ?string $description = null; public bool $is_default = false; public bool $is_active = true;
    public function rules(): array { return ['name'=>'required|string|max:255','code'=>'required|string|max:60','target_group'=>'required|string|max:60','description'=>'nullable|string','is_default'=>'boolean','is_active'=>'boolean']; }
    public function validationAttributes(): array { return ['target_group'=>'target group']; }
    public function normalize(): array { $data = $this->validate(); $data['code'] = str($data['code'])->upper()->toString(); return $data; }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->target_group = 'child'; $this->is_active = true; }
}
