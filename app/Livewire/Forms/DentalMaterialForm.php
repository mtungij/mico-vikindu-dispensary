<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DentalMaterialForm extends Form
{
    public string $name = ''; public string $code = ''; public string $category = 'general'; public string $unit = 'pcs'; public ?string $description = null; public bool $track_inventory = false; public ?int $medicine_id = null; public ?int $service_id = null; public bool $is_active = true;
    public function rules(): array { return ['name'=>['required','string','max:255'],'code'=>['required','alpha_dash','max:40'],'category'=>['required','string'],'unit'=>['required','string'],'description'=>['nullable','string'],'track_inventory'=>['boolean'],'medicine_id'=>['nullable','integer'],'service_id'=>['nullable','integer'],'is_active'=>['boolean']]; }
    public function validationAttributes(): array { return []; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { foreach (array_keys($this->rules()) as $k) $this->{$k}=$m->{$k}; }
    public function resetForm(): void { $this->reset(); $this->category='general'; $this->unit='pcs'; $this->is_active=true; }
}
