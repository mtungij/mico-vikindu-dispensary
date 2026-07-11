<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class MedicineRouteForm extends Form { public ?int $id = null; public string $name = ''; public string $code = ''; public ?string $description = null; public bool $is_active = true; public int $sort_order = 0; public function rules(): array { return ['name' => ['required'], 'code' => ['required']]; } public function validationAttributes(): array { return ['name' => 'route']; } public function normalize(): array { return ['name' => $this->name, 'code' => str($this->code)->upper()->toString(), 'description' => $this->description, 'is_active' => $this->is_active, 'sort_order' => $this->sort_order]; } public function fillFromModel($model): void { $this->id = $model->id; $this->fill($model->only(array_keys($this->normalize()))); } public function resetForm(): void { $this->reset(); $this->is_active = true; } }
