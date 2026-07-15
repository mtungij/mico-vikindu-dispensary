<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class ImmunizationAdministrationForm extends Form
{
    public ?int $vaccine_id = null; public ?int $immunization_schedule_item_id = null; public ?int $medicine_batch_id = null; public ?string $administration_date = null; public string $status = 'administered'; public ?string $reason_not_given = null; public ?string $administration_site = null; public ?string $notes = null;
    public function rules(): array { return ['vaccine_id'=>'required|exists:vaccines,id','immunization_schedule_item_id'=>'nullable|exists:immunization_schedule_items,id','medicine_batch_id'=>'nullable|exists:medicine_batches,id','administration_date'=>'required|date','status'=>'required|string|max:40','reason_not_given'=>'nullable|required_unless:status,administered|string','administration_site'=>'nullable|string|max:255','notes'=>'nullable|string']; }
    public function validationAttributes(): array { return ['vaccine_id'=>'vaccine']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->status = 'administered'; }
}
