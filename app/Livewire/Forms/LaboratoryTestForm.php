<?php

namespace App\Livewire\Forms;

use App\Models\LaboratoryTest;
use Livewire\Form;

class LaboratoryTestForm extends Form
{
    public ?int $id = null; public ?int $service_id = null; public ?int $laboratory_test_category_id = null; public ?int $specimen_type_id = null; public string $name = ''; public string $code = ''; public ?string $short_name = null; public ?string $methodology = null; public string $result_type = 'numeric'; public ?string $unit = null; public ?string $default_reference_range = null; public ?int $decimal_places = null; public ?int $turnaround_time_minutes = null; public bool $requires_fasting = false; public bool $is_panel = false; public bool $is_outsourced = false; public ?string $outsourced_provider = null; public ?string $critical_low = null; public ?string $critical_high = null; public bool $reportable = true; public bool $is_active = true;
    public function rules(): array { return ['laboratory_test_category_id' => ['required', 'integer'], 'service_id' => ['nullable', 'integer'], 'name' => ['required'], 'code' => ['required'], 'result_type' => ['required'], 'critical_low' => ['nullable', 'numeric'], 'critical_high' => ['nullable', 'numeric']]; }
    public function validationAttributes(): array { return ['laboratory_test_category_id' => 'category']; }
    public function normalize(): array { return ['service_id' => $this->service_id, 'laboratory_test_category_id' => $this->laboratory_test_category_id, 'specimen_type_id' => $this->specimen_type_id, 'name' => $this->name, 'code' => str($this->code)->upper()->toString(), 'short_name' => $this->short_name, 'methodology' => $this->methodology, 'result_type' => $this->result_type, 'unit' => $this->unit, 'default_reference_range' => $this->default_reference_range, 'decimal_places' => $this->decimal_places, 'turnaround_time_minutes' => $this->turnaround_time_minutes, 'requires_fasting' => $this->requires_fasting, 'is_panel' => $this->is_panel, 'is_outsourced' => $this->is_outsourced, 'outsourced_provider' => $this->outsourced_provider, 'critical_low' => $this->critical_low, 'critical_high' => $this->critical_high, 'reportable' => $this->reportable, 'is_active' => $this->is_active]; }
    public function fillFromModel(LaboratoryTest $model): void { $this->fill($model->only(array_keys($this->normalize()))); $this->id = $model->id; }
    public function resetForm(): void { $this->reset(); $this->result_type = 'numeric'; $this->reportable = true; $this->is_active = true; }
}
