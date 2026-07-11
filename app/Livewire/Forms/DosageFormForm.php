<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DosageFormForm extends Form { public ?int $id = null; public string $name = ''; public string $code = ''; public ?string $description = null; public bool $is_liquid = false; public bool $is_injectable = false; public bool $is_active = true; public int $sort_order = 0; public function rules(): array { return ['name' => ['required'], 'code' => ['required']]; } public function validationAttributes(): array { return ['name' => 'dosage form']; } public function normalize(): array { return ['name' => $this->name, 'code' => str($this->code)->upper()->toString(), 'description' => $this->description, 'is_liquid' => $this->is_liquid, 'is_injectable' => $this->is_injectable, 'is_active' => $this->is_active, 'sort_order' => $this->sort_order]; } public function fillFromModel($model): void { $this->id = $model->id; $this->fill($model->only(array_keys($this->normalize()))); } public function resetForm(): void { $this->reset(); $this->is_active = true; } }
