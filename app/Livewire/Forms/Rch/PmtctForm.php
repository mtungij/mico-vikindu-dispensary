<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class PmtctForm extends Form
{
    public string $hiv_test_status = 'unknown'; public ?string $test_date = null; public string $result_status = 'not_disclosed'; public ?string $disclosure_status = null; public ?string $partner_testing_status = null; public ?string $linkage_status = null; public ?string $confidential_notes = null;
    public function rules(): array { return ['hiv_test_status'=>'required|string|max:40','test_date'=>'nullable|date','result_status'=>'required|string|max:40','disclosure_status'=>'nullable|string|max:80','partner_testing_status'=>'nullable|string|max:80','linkage_status'=>'nullable|string|max:80','confidential_notes'=>'nullable|string']; }
    public function validationAttributes(): array { return ['hiv_test_status'=>'HIV test status']; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->hiv_test_status = 'unknown'; $this->result_status = 'not_disclosed'; }
}
