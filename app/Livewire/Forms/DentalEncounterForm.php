<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DentalEncounterForm extends Form
{
    public ?string $complaint = null; public ?string $dental_history = null; public ?string $medical_history_review = null; public ?string $oral_hygiene_history = null; public ?string $previous_dental_treatment = null; public ?string $tobacco_use = null; public ?string $alcohol_use = null; public ?string $brushing_frequency = null; public ?string $flossing_frequency = null; public ?string $dental_anxiety_level = null; public ?string $extraoral_examination = null; public ?string $intraoral_examination = null; public ?string $periodontal_summary = null; public ?string $occlusion_summary = null; public ?string $radiographic_findings = null; public ?string $clinical_summary = null; public ?string $treatment_plan_summary = null;
    public function rules(): array { return ['complaint'=>['nullable','string','max:2000'],'dental_history'=>['nullable','string'],'medical_history_review'=>['nullable','string'],'oral_hygiene_history'=>['nullable','string'],'previous_dental_treatment'=>['nullable','string'],'clinical_summary'=>['nullable','string'],'treatment_plan_summary'=>['nullable','string']]; }
    public function validationAttributes(): array { return ['complaint'=>'complaint']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { foreach (array_keys(get_object_vars($this)) as $key) if (isset($model->{$key})) $this->{$key} = $model->{$key}; }
    public function resetForm(): void { $this->reset(); }
}
