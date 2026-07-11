<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DentalExaminationForm extends Form
{
    public string $examination_type = 'extraoral'; public string $area = 'other'; public ?string $status = null; public ?string $findings = null; public ?string $severity = null;
    public function rules(): array { return ['examination_type'=>['required','string'],'area'=>['required','string'],'status'=>['nullable','string'],'findings'=>['nullable','string'],'severity'=>['nullable','string']]; }
    public function validationAttributes(): array { return []; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { foreach (['examination_type','area','status','findings','severity'] as $k) $this->{$k}=$m->{$k}; }
    public function resetForm(): void { $this->reset(); $this->examination_type='extraoral'; $this->area='other'; }
}
