<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class PurchaseReceiptForm extends Form { public ?int $supplier_id = null; public ?int $purchase_order_id = null; public ?int $stock_location_id = null; public ?string $supplier_invoice_number = null; public ?string $supplier_delivery_note = null; public ?string $received_at = null; public ?string $notes = null; public array $items = []; public function rules(): array { return ['supplier_id' => ['required', 'integer'], 'stock_location_id' => ['required', 'integer'], 'items' => ['array']]; } public function validationAttributes(): array { return ['stock_location_id' => 'receiving location']; } public function normalize(): array { return $this->all(); } public function fillFromModel($model): void {} public function resetForm(): void { $this->reset(); } }
