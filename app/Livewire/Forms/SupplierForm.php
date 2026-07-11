<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class SupplierForm extends Form { public ?int $id = null; public string $name = ''; public string $code = ''; public string $supplier_type = 'pharmaceutical_wholesaler'; public ?string $contact_person = null; public string $phone_primary = ''; public ?string $phone_secondary = null; public ?string $email = null; public ?string $physical_address = null; public ?string $notes = null; public ?string $description = null; public bool $is_active = true; public function rules(): array { return ['name' => ['required'], 'code' => ['required'], 'phone_primary' => ['required']]; } public function validationAttributes(): array { return ['name' => 'supplier']; } public function normalize(): array { return ['name' => $this->name, 'code' => str($this->code)->upper()->toString(), 'supplier_type' => $this->supplier_type, 'contact_person' => $this->contact_person, 'phone_primary' => $this->phone_primary, 'phone_secondary' => $this->phone_secondary, 'email' => $this->email, 'physical_address' => $this->physical_address, 'notes' => $this->notes, 'is_active' => $this->is_active]; } public function fillFromModel($model): void { $this->id = $model->id; $this->fill($model->only(array_keys($this->normalize()))); } public function resetForm(): void { $this->reset(); $this->supplier_type = 'pharmaceutical_wholesaler'; $this->is_active = true; } }
