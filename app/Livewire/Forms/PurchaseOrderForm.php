<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class PurchaseOrderForm extends Form { public ?int $supplier_id = null; public ?string $order_date = null; public ?string $expected_delivery_date = null; public ?string $notes = null; public array $items = []; public function rules(): array { return ['supplier_id' => ['required', 'integer'], 'items' => ['array']]; } public function validationAttributes(): array { return ['supplier_id' => 'supplier']; } public function normalize(): array { return $this->all(); } public function fillFromModel($model): void {} public function resetForm(): void { $this->reset(); } }
