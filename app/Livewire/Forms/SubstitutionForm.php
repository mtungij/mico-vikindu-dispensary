<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class SubstitutionForm extends Form { public ?int $prescription_item_id = null; public ?int $medicine_id = null; public ?string $reason = null; public function rules(): array { return ['prescription_item_id' => ['required', 'integer'], 'medicine_id' => ['required', 'integer'], 'reason' => ['required']]; } public function validationAttributes(): array { return ['reason' => 'sababu']; } public function normalize(): array { return $this->all(); } public function fillFromModel($model): void {} public function resetForm(): void { $this->reset(); } }
