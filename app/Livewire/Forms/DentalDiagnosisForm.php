<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DentalDiagnosisForm extends Form
{
    public ?string $tooth_number = null; public ?string $surface = null; public string $diagnosis_type = 'dental'; public string $diagnosis_name = ''; public ?string $icd10_code = null; public string $certainty = 'provisional'; public bool $is_primary = false; public string $status = 'active'; public ?string $notes = null;
    public function rules(): array { return ['tooth_number'=>['nullable','string','max:8'],'surface'=>['nullable','string'],'diagnosis_type'=>['required','string'],'diagnosis_name'=>['required','string','max:255'],'icd10_code'=>['nullable','string','max:20'],'certainty'=>['required','string'],'is_primary'=>['boolean'],'status'=>['required','string'],'notes'=>['nullable','string']]; }
    public function validationAttributes(): array { return ['diagnosis_name'=>'diagnosis']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { foreach (array_keys($this->rules()) as $k) $this->{$k}=$m->{$k}; }
    public function resetForm(): void { $this->reset(); $this->diagnosis_type='dental'; $this->certainty='provisional'; $this->status='active'; }
}
