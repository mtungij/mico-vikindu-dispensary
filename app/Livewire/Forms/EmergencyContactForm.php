<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class EmergencyContactForm extends Form
{
    public string $full_name = '';
    public string $relationship = '';
    public string $primary_phone = '';
    public ?string $secondary_phone = null;
    public ?string $email = null;
    public ?string $physical_address = null;
    public bool $is_primary = false;
    public ?string $notes = null;

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'relationship' => ['required', 'string', 'max:80'],
            'primary_phone' => ['required', 'string', 'max:30'],
            'secondary_phone' => ['nullable', 'string', 'max:30', 'different:primary_phone'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'physical_address' => ['nullable', 'string', 'max:1000'],
            'is_primary' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function data(): array
    {
        return $this->validate();
    }
}
