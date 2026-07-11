<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class StockLocationForm extends Form { public ?int $id = null; public ?int $department_id = null; public string $name = ''; public string $code = ''; public string $location_type = 'pharmacy'; public ?string $description = null; public bool $is_dispensing_location = false; public bool $is_receiving_location = false; public bool $allows_transfers = true; public bool $is_active = true; public function rules(): array { return ['name' => ['required'], 'code' => ['required']]; } public function validationAttributes(): array { return ['name' => 'location']; } public function normalize(): array { return ['department_id' => $this->department_id, 'name' => $this->name, 'code' => str($this->code)->upper()->toString(), 'location_type' => $this->location_type, 'description' => $this->description, 'is_dispensing_location' => $this->is_dispensing_location, 'is_receiving_location' => $this->is_receiving_location, 'allows_transfers' => $this->allows_transfers, 'is_active' => $this->is_active]; } public function fillFromModel($model): void { $this->id = $model->id; $this->fill($model->only(array_keys($this->normalize()))); } public function resetForm(): void { $this->reset(); $this->location_type = 'pharmacy'; $this->allows_transfers = true; $this->is_active = true; } }
