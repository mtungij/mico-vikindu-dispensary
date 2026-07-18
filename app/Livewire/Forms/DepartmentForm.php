<?php

namespace App\Livewire\Forms;

use App\Enums\DepartmentType;
use App\Models\Department;
use Illuminate\Validation\Rule;
use Livewire\Form;

class DepartmentForm extends Form
{
    public ?int $id = null;
    public string $name = '';
    public string $code = '';
    public ?string $description = null;
    public ?string $department_type = null;
    public ?string $icon = null;
    public ?string $color = '#0f766e';
    public ?string $phone_extension = null;
    public ?string $location = null;
    public bool $queue_enabled = false;
    public bool $billing_enabled = false;
    public bool $clinical_department = false;
    public bool $stock_location_enabled = false;
    public bool $can_receive_patients = true;
    public bool $requires_consultation = false;
    public bool $requires_triage = false;
    public bool $is_active = true;
    public int $sort_order = 0;

    public function setDepartment(Department $department): void
    {
        $this->id = $department->id;
        $this->name = $department->name;
        $this->code = $department->code;
        $this->description = $department->description;
        $this->department_type = $department->department_type?->value;
        $this->icon = $department->icon;
        $this->color = $department->color;
        $this->phone_extension = $department->phone_extension;
        $this->location = $department->location;
        $this->queue_enabled = $department->queue_enabled;
        $this->billing_enabled = $department->billing_enabled;
        $this->clinical_department = $department->clinical_department;
        $this->stock_location_enabled = $department->stock_location_enabled;
        $this->can_receive_patients = $department->can_receive_patients;
        $this->requires_consultation = $department->requires_consultation;
        $this->requires_triage = $department->requires_triage;
        $this->is_active = $department->is_active;
        $this->sort_order = $department->sort_order;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $facilityId = currentFacility()?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('departments', 'name')->where('facility_id', $facilityId)->ignore($this->id),
            ],
            'code' => [
                'required',
                'alpha_dash',
                'max:20',
                Rule::unique('departments', 'code')->where('facility_id', $facilityId)->ignore($this->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'department_type' => ['nullable', Rule::enum(DepartmentType::class)],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'phone_extension' => ['nullable', 'string', 'max:20'],
            'location' => ['nullable', 'string', 'max:100'],
            'queue_enabled' => ['boolean'],
            'billing_enabled' => ['boolean'],
            'clinical_department' => ['boolean'],
            'stock_location_enabled' => ['boolean'],
            'can_receive_patients' => ['boolean'],
            'requires_consultation' => ['boolean'],
            'requires_triage' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $validated = $this->validate();
        $validated['code'] = str($validated['code'])->upper()->toString();

        return $validated;
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->color = '#0f766e';
        $this->is_active = true;
    }
}
