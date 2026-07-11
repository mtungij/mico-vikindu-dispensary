<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class MedicineCategoryForm extends Form { public ?int $id = null; public string $name = ''; public string $code = ''; public ?string $description = null; public ?int $parent_id = null; public ?string $icon = null; public ?string $color = null; public int $sort_order = 0; public bool $is_active = true; public function rules(): array { return ['name' => ['required'], 'code' => ['required'], 'parent_id' => ['nullable', 'integer'], 'is_active' => ['boolean']]; } public function validationAttributes(): array { return ['name' => 'jina', 'code' => 'code']; } public function normalize(): array { return ['name' => $this->name, 'code' => str($this->code)->upper()->toString(), 'description' => $this->description, 'parent_id' => $this->parent_id, 'icon' => $this->icon, 'color' => $this->color, 'sort_order' => $this->sort_order, 'is_active' => $this->is_active]; } public function fillFromModel($model): void { $this->id = $model->id; $this->fill($model->only(array_keys($this->normalize()))); } public function resetForm(): void { $this->reset(); $this->is_active = true; } }
