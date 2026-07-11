<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class StockTransferForm extends Form { public ?int $from_location_id = null; public ?int $to_location_id = null; public ?string $notes = null; public array $items = []; public function rules(): array { return ['from_location_id' => ['required', 'integer'], 'to_location_id' => ['required', 'integer', 'different:from_location_id'], 'items' => ['array']]; } public function validationAttributes(): array { return ['from_location_id' => 'from location']; } public function normalize(): array { return $this->all(); } public function fillFromModel($model): void {} public function resetForm(): void { $this->reset(); } }
