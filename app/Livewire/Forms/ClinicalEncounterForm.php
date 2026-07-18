<?php

namespace App\Livewire\Forms;

use App\Models\ClinicalEncounter;
use Livewire\Form;

class ClinicalEncounterForm extends Form
{
    public ?string $chief_complaint = null;
    public ?string $history_of_presenting_illness = null;
    public ?string $past_medical_history = null;
    public ?string $surgical_history = null;
    public ?string $medication_history = null;
    public ?string $allergy_history = null;
    public ?string $family_history = null;
    public ?string $social_history = null;
    public ?string $review_of_systems = null;
    public ?string $physical_examination = null;
    public ?string $clinical_summary = null;
    public ?string $assessment_notes = null;
    public ?string $treatment_plan = null;
    public ?string $discharge_instructions = null;
    public bool $follow_up_required = false;
    public ?string $follow_up_date = null;
    public ?string $outcome = 'ongoing';

    public function rules(): array
    {
        return [
            'chief_complaint' => ['nullable', 'string', 'max:4000'],
            'history_of_presenting_illness' => ['nullable', 'string'],
            'physical_examination' => ['nullable', 'string'],
            'clinical_summary' => ['nullable', 'string'],
            'assessment_notes' => ['nullable', 'string'],
            'treatment_plan' => ['nullable', 'string'],
            'follow_up_required' => ['boolean'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:today'],
            'outcome' => ['nullable', 'string'],
        ];
    }

    public function validationAttributes(): array { return ['history_of_presenting_illness' => 'historia ya tatizo la sasa']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel(ClinicalEncounter $encounter): void
    {
        $data = $encounter->only(array_keys($this->normalize()));
        $data['follow_up_required'] = (bool) ($data['follow_up_required'] ?? false);
        $this->fill($data);
    }
    public function resetForm(): void { $this->reset(); $this->outcome = 'ongoing'; }
}
