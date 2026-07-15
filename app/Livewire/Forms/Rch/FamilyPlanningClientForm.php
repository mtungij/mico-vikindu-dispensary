<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class FamilyPlanningClientForm extends Form
{
    public ?int $patient_id = null; public ?string $registration_date = null; public string $client_type = 'new'; public ?string $reproductive_intention = null; public ?int $desired_number_of_children = null; public ?string $spacing_preference = null; public ?int $current_method_id = null; public ?string $notes = null;
    public function rules(): array { return ['patient_id'=>'required|exists:patients,id','registration_date'=>'required|date','client_type'=>'required|string|max:60','reproductive_intention'=>'nullable|string|max:255','desired_number_of_children'=>'nullable|integer|min:0|max:20','spacing_preference'=>'nullable|string|max:255','current_method_id'=>'nullable|exists:family_planning_methods,id','notes'=>'nullable|string']; }
    public function validationAttributes(): array { return ['patient_id'=>'patient']; }
    public function normalize(): array { $data = $this->validate(); $data['registration_date'] ??= today()->toDateString(); return $data; }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->client_type = 'new'; }
}
