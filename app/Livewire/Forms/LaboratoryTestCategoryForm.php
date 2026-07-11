<?php

namespace App\Livewire\Forms;

use App\Models\LaboratoryTestCategory;
use Livewire\Form;

class LaboratoryTestCategoryForm extends Form
{
    public ?int $id = null; public string $name = ''; public string $code = ''; public ?string $description = null; public ?string $icon = null; public ?string $color = null; public int $sort_order = 0; public bool $is_active = true;
    public function rules(): array { return ['name' => ['required', 'max:255'], 'code' => ['required', 'max:40'], 'description' => ['nullable', 'string'], 'is_active' => ['boolean']]; }
    public function validationAttributes(): array { return ['name' => 'jina', 'code' => 'code']; }
    public function normalize(): array { return ['name' => $this->name, 'code' => str($this->code)->upper()->toString(), 'description' => $this->description, 'icon' => $this->icon, 'color' => $this->color, 'sort_order' => $this->sort_order, 'is_active' => $this->is_active]; }
    public function fillFromModel(LaboratoryTestCategory $model): void { $this->fill($model->only(['id','name','code','description','icon','color','sort_order','is_active'])); }
    public function resetForm(): void { $this->reset(); $this->is_active = true; }
}
