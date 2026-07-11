<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class SupplierReturnForm extends Form { public ?int $supplier_id = null; public ?int $stock_location_id = null; public string $reason = ''; public ?string $notes = null; public array $items = []; public function rules(): array { return ['supplier_id' => ['required', 'integer'], 'stock_location_id' => ['required', 'integer'], 'reason' => ['required'], 'items' => ['array']]; } public function validationAttributes(): array { return ['reason' => 'sababu']; } public function normalize(): array { return $this->all(); } public function fillFromModel($model): void {} public function resetForm(): void { $this->reset(); } }
