<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class StaffLicenseForm extends Form
{
    public ?string $license_type = null;
    public ?string $professional_body = null;
    public ?string $registration_number = null;
    public ?string $license_number = null;
    public ?string $issue_date = null;
    public ?string $expiry_date = null;
    public ?string $notes = null;

    public function rules(): array
    {
        return [
            'license_type' => ['required', 'string', 'max:120'],
            'professional_body' => ['required', 'string', 'max:150'],
            'registration_number' => ['required', 'string', 'max:120'],
            'license_number' => ['nullable', 'string', 'max:120'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function data(): array
    {
        return $this->validate();
    }
}
