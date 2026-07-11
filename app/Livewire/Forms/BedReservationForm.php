<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class BedReservationForm extends Form { public ?int $bed_id=null; public ?int $patient_id=null; public ?int $visit_id=null; public ?string $expires_at=null; public ?string $notes=null; public function rules(): array { return ['bed_id'=>['required','integer'],'patient_id'=>['required','integer'],'visit_id'=>['required','integer']]; } public function validationAttributes(): array { return ['bed_id'=>'kitanda']; } public function normalize(): array { return $this->all(); } public function fillFromModel($m): void {} public function resetForm(): void { $this->reset(); } }
