<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class ReferralForm extends Form
{
    public string $referral_type = 'external';
    public string $destination_facility_name = '';
    public ?string $destination_department = null;
    public ?string $destination_contact = null;
    public string $reason = '';
    public ?string $provisional_diagnosis = null;
    public ?string $clinical_summary = null;
    public ?string $treatment_given = null;
    public ?string $investigations_done = null;
    public ?string $current_medications = null;
    public string $urgency = 'routine';
    public ?string $transport_method = null;
    public ?string $accompanying_person = null;
    public function rules(): array { return ['referral_type' => ['required'], 'destination_facility_name' => ['required', 'string', 'max:255'], 'reason' => ['required', 'string'], 'urgency' => ['required', 'in:routine,urgent,emergency']]; }
    public function validationAttributes(): array { return ['destination_facility_name' => 'kituo kinachopokea']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->normalize()))); }
    public function resetForm(): void { $this->reset(); $this->referral_type = 'external'; $this->urgency = 'routine'; }
}
