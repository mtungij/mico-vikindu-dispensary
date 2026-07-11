<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class MedicineUnitForm extends Form { public ?int $id = null; public string $name = ''; public string $symbol = ''; public ?string $description = null; public bool $decimal_allowed = false; public bool $is_active = true; public int $sort_order = 0; public function rules(): array { return ['name' => ['required'], 'symbol' => ['required']]; } public function validationAttributes(): array { return ['symbol' => 'unit symbol']; } public function normalize(): array { return ['name' => $this->name, 'symbol' => str($this->symbol)->lower()->toString(), 'description' => $this->description, 'decimal_allowed' => $this->decimal_allowed, 'is_active' => $this->is_active, 'sort_order' => $this->sort_order]; } public function fillFromModel($model): void { $this->id = $model->id; $this->fill($model->only(array_keys($this->normalize()))); } public function resetForm(): void { $this->reset(); $this->is_active = true; } }
