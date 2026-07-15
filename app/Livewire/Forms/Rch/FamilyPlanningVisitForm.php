<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class FamilyPlanningVisitForm extends Form
{
    public ?string $visit_date = null; public string $visit_type = 'follow_up'; public ?int $selected_method_id = null; public ?string $method_start_date = null; public ?string $expected_end_date = null; public bool $counselling_done = false; public ?string $discontinuation_reason = null; public ?string $next_visit_date = null; public ?string $notes = null;
    public function rules(): array { return ['visit_date'=>'required|date','visit_type'=>'required|string|max:60','selected_method_id'=>'nullable|exists:family_planning_methods,id','method_start_date'=>'nullable|date','expected_end_date'=>'nullable|date|after_or_equal:method_start_date','counselling_done'=>'boolean','discontinuation_reason'=>'nullable|string','next_visit_date'=>'nullable|date|after:visit_date','notes'=>'nullable|string']; }
    public function validationAttributes(): array { return ['selected_method_id'=>'selected method']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->visit_type = 'follow_up'; }
}
