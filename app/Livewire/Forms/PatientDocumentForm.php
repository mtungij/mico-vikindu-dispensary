<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class PatientDocumentForm extends Form
{
    public string $document_type = 'other'; public string $document_name = ''; public ?string $document_number = null; public ?string $issue_date = null; public ?string $expiry_date = null; public ?string $notes = null;
    public function rules(): array { return ['document_type' => ['required', 'string'], 'document_name' => ['required', 'string', 'max:150'], 'document_number' => ['nullable', 'string', 'max:100'], 'issue_date' => ['nullable', 'date'], 'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'], 'notes' => ['nullable', 'string']]; }
    public function data(): array { return $this->validate(); }
}
