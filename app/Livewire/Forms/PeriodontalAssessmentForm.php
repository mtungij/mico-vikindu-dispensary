<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class PeriodontalAssessmentForm extends Form
{
    public ?string $assessment_date = null; public ?float $plaque_index = null; public ?float $bleeding_index = null; public ?float $calculus_index = null; public ?string $oral_hygiene_status = null; public ?string $gingival_status = null; public ?string $periodontal_diagnosis = null; public ?string $notes = null;
    public function rules(): array { return ['assessment_date'=>['nullable','date'],'plaque_index'=>['nullable','numeric','between:0,100'],'bleeding_index'=>['nullable','numeric','between:0,100'],'calculus_index'=>['nullable','numeric','between:0,100'],'oral_hygiene_status'=>['nullable','string'],'gingival_status'=>['nullable','string'],'periodontal_diagnosis'=>['nullable','string'],'notes'=>['nullable','string']]; }
    public function validationAttributes(): array { return []; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { foreach (array_keys($this->rules()) as $k) $this->{$k}=$m->{$k}; }
    public function resetForm(): void { $this->reset(); }
}
