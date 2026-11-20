<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class RchEncounterForm extends Form
{
    public ?string $chief_complaint = null;

    public ?string $clinical_summary = null;

    public ?string $treatment_plan = null;

    public function rules(): array
    {
        return [
            'chief_complaint' => ['nullable', 'string', 'max:20000'],
            'clinical_summary' => ['nullable', 'string', 'max:20000'],
            'treatment_plan' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
