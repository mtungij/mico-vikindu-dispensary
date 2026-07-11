<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class PharmacyReturnForm extends Form { public string $reason = ''; public ?int $returned_by_user_id = null; public ?string $returned_at = null; public ?string $notes = null; public array $items = []; public function rules(): array { return ['reason' => ['required'], 'items' => ['array']]; } public function validationAttributes(): array { return ['reason' => 'sababu']; } public function normalize(): array { return $this->all(); } public function fillFromModel($model): void {} public function resetForm(): void { $this->reset(); } }
