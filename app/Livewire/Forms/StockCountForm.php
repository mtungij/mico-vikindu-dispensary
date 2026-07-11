<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class StockCountForm extends Form { public ?int $stock_location_id = null; public ?string $notes = null; public array $counted = []; public function rules(): array { return ['stock_location_id' => ['required', 'integer'], 'counted' => ['array']]; } public function validationAttributes(): array { return ['stock_location_id' => 'location']; } public function normalize(): array { return $this->all(); } public function fillFromModel($model): void {} public function resetForm(): void { $this->reset(); } }
