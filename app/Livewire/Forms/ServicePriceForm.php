<?php

namespace App\Livewire\Forms;

use App\Enums\PayerType;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ServicePriceForm extends Form
{
    public string $payer_type = 'cash';
    public ?int $insurance_provider_id = null;
    public ?int $corporate_account_id = null;
    public string $amount = '0.00';
    public string $currency = 'TZS';
    public ?string $effective_from = null;
    public ?string $effective_to = null;
    public ?string $notes = null;

    public function rules(): array
    {
        $facilityId = currentFacility()?->id;
        return [
            'payer_type' => ['required', Rule::enum(PayerType::class)],
            'insurance_provider_id' => ['nullable', 'required_if:payer_type,insurance', Rule::exists('insurance_providers', 'id')->where('facility_id', $facilityId)],
            'corporate_account_id' => ['nullable', 'required_if:payer_type,corporate', Rule::exists('corporate_accounts', 'id')->where('facility_id', $facilityId)],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function data(): array { return $this->validate(); }
}
