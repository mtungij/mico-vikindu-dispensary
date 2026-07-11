<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class StockAdjustmentForm extends Form { public ?int $stock_location_id = null; public string $adjustment_type = 'correction'; public string $reason = ''; public ?string $notes = null; public array $items = []; public function rules(): array { return ['stock_location_id' => ['required', 'integer'], 'reason' => ['required'], 'items' => ['array']]; } public function validationAttributes(): array { return ['reason' => 'sababu']; } public function normalize(): array { return $this->all(); } public function fillFromModel($model): void {} public function resetForm(): void { $this->reset(); $this->adjustment_type = 'correction'; } }
