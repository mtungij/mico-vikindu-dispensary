<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class BirthPreparednessForm extends Form
{
    public ?string $preferred_delivery_facility = null; public bool $skilled_provider_identified = false; public ?string $transport_plan = null; public bool $funds_prepared = false; public bool $blood_donor_identified = false; public ?string $birth_companion = null; public bool $danger_signs_counselling_done = false; public bool $delivery_supplies_prepared = false; public ?string $notes = null;
    public function rules(): array { return ['preferred_delivery_facility'=>'nullable|string|max:255','skilled_provider_identified'=>'boolean','transport_plan'=>'nullable|string','funds_prepared'=>'boolean','blood_donor_identified'=>'boolean','birth_companion'=>'nullable|string|max:255','danger_signs_counselling_done'=>'boolean','delivery_supplies_prepared'=>'boolean','notes'=>'nullable|string']; }
    public function validationAttributes(): array { return ['preferred_delivery_facility'=>'delivery facility']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); }
}
