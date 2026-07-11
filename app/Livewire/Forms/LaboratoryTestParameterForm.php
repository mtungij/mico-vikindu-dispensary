<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class LaboratoryTestParameterForm extends Form
{
    public string $name = ''; public string $code = ''; public string $result_type = 'numeric'; public ?string $unit = null; public ?string $default_reference_range = null; public ?string $critical_low = null; public ?string $critical_high = null; public ?string $allowed_values_text = null; public bool $is_required = true; public bool $is_heading = false; public bool $show_on_report = true; public int $sort_order = 0;
    public function rules(): array { return ['name' => ['required'], 'code' => ['required'], 'result_type' => ['required'], 'critical_low' => ['nullable', 'numeric'], 'critical_high' => ['nullable', 'numeric']]; }
    public function validationAttributes(): array { return ['name' => 'parameter']; }
    public function normalize(): array { return ['name' => $this->name, 'code' => str($this->code)->upper()->toString(), 'result_type' => $this->result_type, 'unit' => $this->unit, 'default_reference_range' => $this->default_reference_range, 'critical_low' => $this->critical_low, 'critical_high' => $this->critical_high, 'allowed_values' => $this->allowed_values_text ? array_map('trim', explode(',', $this->allowed_values_text)) : null, 'is_required' => $this->is_required, 'is_heading' => $this->is_heading, 'show_on_report' => $this->show_on_report, 'sort_order' => $this->sort_order]; }
    public function fillFromModel($model): void { $this->fill($model->only(['name','code','result_type','unit','default_reference_range','critical_low','critical_high','is_required','is_heading','show_on_report','sort_order'])); $this->allowed_values_text = implode(',', $model->allowed_values ?? []); }
    public function resetForm(): void { $this->reset(); $this->result_type = 'numeric'; $this->is_required = true; $this->show_on_report = true; }
}
