<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class ChildNutritionAssessmentForm extends Form
{
    public string $overall_nutrition_status = 'indeterminate'; public ?string $feeding_counselling = null; public ?string $nutrition_plan = null; public bool $referral_required = false;
    public function rules(): array { return ['overall_nutrition_status'=>'required|string|max:60','feeding_counselling'=>'nullable|string','nutrition_plan'=>'nullable|string','referral_required'=>'boolean']; }
    public function validationAttributes(): array { return ['overall_nutrition_status'=>'nutrition status']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->overall_nutrition_status = 'indeterminate'; }
}
