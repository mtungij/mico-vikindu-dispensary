<?php

namespace App\Livewire\Forms;

use App\Enums\PayerType;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PatientPayerForm extends Form
{
    public string $payer_type = 'cash'; public ?int $insurance_provider_id = null; public ?int $corporate_account_id = null; public ?string $membership_number = null; public ?string $card_number = null; public ?string $principal_member_name = null; public ?string $relationship_to_principal = null; public ?string $authorization_number = null; public ?string $scheme_name = null; public ?string $valid_from = null; public ?string $valid_to = null; public string $coverage_status = 'active'; public bool $is_primary = true; public ?string $notes = null;
    public function rules(): array
    {
        $facilityId = currentFacility()?->id;
        return ['payer_type' => ['required', Rule::enum(PayerType::class)], 'insurance_provider_id' => ['nullable', 'required_if:payer_type,insurance', Rule::exists('insurance_providers', 'id')->where('facility_id', $facilityId)], 'corporate_account_id' => ['nullable', 'required_if:payer_type,corporate', Rule::exists('corporate_accounts', 'id')->where('facility_id', $facilityId)], 'membership_number' => ['nullable', 'string', 'max:100'], 'card_number' => ['nullable', 'string', 'max:100'], 'principal_member_name' => ['nullable', 'string', 'max:150'], 'relationship_to_principal' => ['nullable', 'string', 'max:100'], 'authorization_number' => ['nullable', 'string', 'max:100'], 'scheme_name' => ['nullable', 'string', 'max:100'], 'valid_from' => ['nullable', 'date'], 'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'], 'coverage_status' => ['required', 'string'], 'is_primary' => ['boolean'], 'notes' => ['nullable', 'string']];
    }
    public function data(): array { return $this->validate(); }
}
