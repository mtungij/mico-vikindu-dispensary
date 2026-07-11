<?php

namespace App\Livewire\Forms;

use App\Enums\ServiceType;
use App\Models\Service;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ServiceForm extends Form
{
    public ?int $id = null;
    public ?int $service_category_id = null;
    public ?int $department_id = null;
    public string $name = '';
    public string $code = '';
    public string $service_type = 'other';
    public ?string $description = null;
    public ?int $duration_minutes = null;
    public bool $requires_clinical_order = false;
    public bool $requires_payment = true;
    public bool $requires_appointment = false;
    public bool $allows_walk_in = true;
    public bool $taxable = false;
    public bool $queue_enabled = false;
    public bool $stock_related = false;
    public bool $is_active = true;
    public int $sort_order = 0;

    public function setModel(Service $service): void
    {
        foreach (array_keys(get_object_vars($this)) as $property) {
            if (isset($service->{$property})) {
                $this->{$property} = $service->{$property}?->value ?? $service->{$property};
            }
        }
        $this->id = $service->id;
    }

    public function rules(): array
    {
        $facilityId = currentFacility()?->id;
        return [
            'service_category_id' => ['required', Rule::exists('service_categories', 'id')->where('facility_id', $facilityId)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('facility_id', $facilityId)],
            'name' => ['required', 'string', 'max:120', Rule::unique('services', 'name')->where('facility_id', $facilityId)->ignore($this->id)],
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('services', 'code')->where('facility_id', $facilityId)->ignore($this->id)],
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'requires_clinical_order' => ['boolean'], 'requires_payment' => ['boolean'], 'requires_appointment' => ['boolean'],
            'allows_walk_in' => ['boolean'], 'taxable' => ['boolean'], 'queue_enabled' => ['boolean'], 'stock_related' => ['boolean'], 'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function data(): array { $data = $this->validate(); $data['code'] = str($data['code'])->upper()->toString(); return $data; }
    public function resetForm(): void { $this->reset(); $this->service_type = 'other'; $this->requires_payment = true; $this->allows_walk_in = true; $this->is_active = true; }
}
