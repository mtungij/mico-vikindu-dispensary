<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DentalConsentForm extends Form
{
    public string $consent_type = 'general_dental_treatment'; public string $consent_text_snapshot = ''; public ?string $risks_explained = null; public ?string $alternatives_explained = null; public string $patient_or_guardian_name = ''; public ?string $relationship_to_patient = null; public bool $consent_given = false;
    public function rules(): array { return ['consent_type'=>['required','string'],'consent_text_snapshot'=>['required','string'],'risks_explained'=>['nullable','string'],'alternatives_explained'=>['nullable','string'],'patient_or_guardian_name'=>['required','string','max:255'],'relationship_to_patient'=>['nullable','string'],'consent_given'=>['boolean']]; }
    public function validationAttributes(): array { return []; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { foreach (array_keys($this->rules()) as $k) $this->{$k}=$m->{$k}; }
    public function resetForm(): void { $this->reset(); $this->consent_type='general_dental_treatment'; }
}
