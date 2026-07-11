<?php

namespace App\Livewire\Forms;

use App\Enums\PayerType;
use App\Enums\VisitPriority;
use App\Enums\VisitType;
use Illuminate\Validation\Rule;
use Livewire\Form;

class VisitForm extends Form
{
    public string $visit_type = 'new_patient'; public string $payer_type = 'cash'; public ?int $destination_department_id = null; public ?int $consultation_service_id = null; public string $priority = 'normal'; public string $source = 'walk_in'; public ?string $reason_for_visit = null; public bool $require_payment_before_service = true;
    public function rules(): array
    {
        $facilityId = currentFacility()?->id;
        return ['visit_type' => ['required', Rule::enum(VisitType::class)], 'payer_type' => ['required', Rule::enum(PayerType::class)], 'destination_department_id' => ['required', Rule::exists('departments', 'id')->where('facility_id', $facilityId)->where('is_active', true)], 'consultation_service_id' => ['nullable', Rule::exists('services', 'id')->where('facility_id', $facilityId)->where('is_active', true)->where('service_type', 'consultation')], 'priority' => ['required', Rule::enum(VisitPriority::class)], 'source' => ['required', 'string'], 'reason_for_visit' => ['nullable', 'string', 'max:1000'], 'require_payment_before_service' => ['boolean']];
    }
    public function data(): array { return $this->validate(); }
}
