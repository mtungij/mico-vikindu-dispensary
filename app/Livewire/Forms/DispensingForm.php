<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DispensingForm extends Form { public ?int $stock_location_id = null; public ?string $override_reason = null; public array $lines = []; public function rules(): array { return ['stock_location_id' => ['required', 'integer'], 'lines' => ['array']]; } public function validationAttributes(): array { return ['stock_location_id' => 'dispensing location']; } public function normalize(): array { return $this->all(); } public function fillFromModel($model): void {} public function resetForm(): void { $this->reset(); } }
