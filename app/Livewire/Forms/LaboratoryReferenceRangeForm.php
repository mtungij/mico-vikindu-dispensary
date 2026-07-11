<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class LaboratoryReferenceRangeForm extends Form
{
    public ?int $laboratory_test_parameter_id = null; public ?string $gender = null; public ?int $minimum_age_days = null; public ?int $maximum_age_days = null; public ?string $pregnancy_status = null; public ?string $lower_limit = null; public ?string $upper_limit = null; public ?string $textual_range = null; public ?string $unit = null; public ?string $interpretation = null; public bool $is_active = true; public int $priority = 0;
    public function rules(): array { return ['lower_limit' => ['nullable', 'numeric'], 'upper_limit' => ['nullable', 'numeric'], 'minimum_age_days' => ['nullable', 'integer'], 'maximum_age_days' => ['nullable', 'integer']]; }
    public function validationAttributes(): array { return ['lower_limit' => 'lower limit']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->normalize()))); }
    public function resetForm(): void { $this->reset(); $this->is_active = true; }
}
