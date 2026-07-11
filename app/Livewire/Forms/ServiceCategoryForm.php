<?php

namespace App\Livewire\Forms;

use App\Enums\ServiceCategoryType;
use App\Models\ServiceCategory;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ServiceCategoryForm extends Form
{
    public ?int $id = null;
    public string $name = '';
    public string $code = '';
    public string $category_type = 'other';
    public ?int $department_id = null;
    public ?string $description = null;
    public ?string $icon = null;
    public ?string $color = '#0f766e';
    public int $sort_order = 0;
    public bool $is_active = true;

    public function setModel(ServiceCategory $category): void
    {
        $this->id = $category->id; $this->name = $category->name; $this->code = $category->code;
        $this->category_type = $category->category_type->value; $this->department_id = $category->department_id;
        $this->description = $category->description; $this->icon = $category->icon; $this->color = $category->color;
        $this->sort_order = $category->sort_order; $this->is_active = $category->is_active;
    }

    public function rules(): array
    {
        $facilityId = currentFacility()?->id;
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('service_categories', 'name')->where('facility_id', $facilityId)->ignore($this->id)],
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('service_categories', 'code')->where('facility_id', $facilityId)->ignore($this->id)],
            'category_type' => ['required', Rule::enum(ServiceCategoryType::class)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('facility_id', $facilityId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function data(): array { $data = $this->validate(); $data['code'] = str($data['code'])->upper()->toString(); return $data; }
    public function resetForm(): void { $this->reset(); $this->category_type = 'other'; $this->color = '#0f766e'; $this->is_active = true; }
}
