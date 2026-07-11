<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DentalTreatmentPlanForm extends Form
{
    public string $title = ''; public ?string $description = null; public ?string $priority = null; public ?string $planned_start_date = null; public ?string $expected_completion_date = null; public bool $consent_required = false;
    public function rules(): array { return ['title'=>['required','string','max:255'],'description'=>['nullable','string'],'priority'=>['nullable','string'],'planned_start_date'=>['nullable','date'],'expected_completion_date'=>['nullable','date','after_or_equal:planned_start_date'],'consent_required'=>['boolean']]; }
    public function validationAttributes(): array { return ['title'=>'jina la mpango']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { foreach (array_keys($this->rules()) as $k) $this->{$k}=$m->{$k}; }
    public function resetForm(): void { $this->reset(); }
}
