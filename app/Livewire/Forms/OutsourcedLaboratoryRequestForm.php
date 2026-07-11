<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class OutsourcedLaboratoryRequestForm extends Form
{
    public string $external_provider_name = ''; public ?string $external_reference_number = null; public ?string $expected_at = null; public ?string $notes = null;
    public function rules(): array { return ['external_provider_name' => ['required'], 'expected_at' => ['nullable', 'date']]; }
    public function validationAttributes(): array { return ['external_provider_name' => 'external provider']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void {}
    public function resetForm(): void { $this->reset(); }
}
