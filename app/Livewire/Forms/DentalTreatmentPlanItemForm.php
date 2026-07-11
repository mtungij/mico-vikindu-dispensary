<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DentalTreatmentPlanItemForm extends Form
{
    public ?int $service_id = null; public ?string $tooth_number = null; public array $surfaces = []; public float $quantity = 1; public int $sequence_order = 0; public ?string $notes = null;
    public function rules(): array { return ['service_id'=>['required','integer'],'tooth_number'=>['nullable','string','max:8'],'surfaces'=>['array'],'quantity'=>['required','numeric','min:0.01'],'sequence_order'=>['integer','min:0'],'notes'=>['nullable','string']]; }
    public function validationAttributes(): array { return []; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { foreach (array_keys($this->rules()) as $k) if (isset($m->{$k})) $this->{$k}=$m->{$k}; }
    public function resetForm(): void { $this->reset(); $this->surfaces=[]; $this->quantity=1; }
}
