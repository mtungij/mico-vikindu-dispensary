<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class BedTransferForm extends Form { public ?int $destination_bed_id=null; public string $reason=''; public ?string $notes=null; public function rules(): array { return ['destination_bed_id'=>['required','integer'],'reason'=>['required']]; } public function validationAttributes(): array { return ['reason'=>'sababu']; } public function normalize(): array { return $this->all(); } public function fillFromModel($m): void {} public function resetForm(): void { $this->reset(); } }
