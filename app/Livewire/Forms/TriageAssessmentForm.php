<?php

namespace App\Livewire\Forms;

use App\Enums\ConsciousnessLevel;
use App\Enums\PregnancyStatus;
use App\Enums\TriageLevel;
use App\Models\TriageAssessment;
use Livewire\Form;

class TriageAssessmentForm extends Form
{
    public ?int $assessmentId = null;
    public string $triage_level = 'routine';
    public ?string $chief_complaint_summary = null;
    public ?string $temperature = null;
    public ?int $systolic_bp = null;
    public ?int $diastolic_bp = null;
    public ?int $pulse_rate = null;
    public ?int $respiratory_rate = null;
    public ?string $oxygen_saturation = null;
    public ?string $weight_kg = null;
    public ?string $height_cm = null;
    public ?string $blood_glucose = null;
    public ?string $muac_cm = null;
    public ?int $pain_score = null;
    public ?string $consciousness_level = null;
    public ?string $pregnancy_status = 'not_applicable';
    public ?int $gestational_age_weeks = null;
    public array $danger_signs = [];
    public bool $allergies_confirmed = false;
    public ?string $fall_risk = null;
    public ?string $infection_risk = null;
    public ?string $notes = null;

    public function rules(): array
    {
        return [
            'triage_level' => ['required', 'in:'.collect(TriageLevel::cases())->pluck('value')->implode(',')],
            'chief_complaint_summary' => ['nullable', 'string', 'max:2000'],
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'systolic_bp' => ['nullable', 'integer', 'between:40,280'],
            'diastolic_bp' => ['nullable', 'integer', 'between:20,180'],
            'pulse_rate' => ['nullable', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:4,80'],
            'oxygen_saturation' => ['nullable', 'numeric', 'between:0,100'],
            'weight_kg' => ['nullable', 'numeric', 'between:0.5,350'],
            'height_cm' => ['nullable', 'numeric', 'between:20,250'],
            'blood_glucose' => ['nullable', 'numeric', 'between:0,60'],
            'muac_cm' => ['nullable', 'numeric', 'between:1,80'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
            'consciousness_level' => ['nullable', 'in:'.collect(ConsciousnessLevel::cases())->pluck('value')->implode(',')],
            'pregnancy_status' => ['nullable', 'in:'.collect(PregnancyStatus::cases())->pluck('value')->implode(',')],
            'gestational_age_weeks' => ['nullable', 'integer', 'between:1,45'],
            'danger_signs' => ['array'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function validationAttributes(): array
    {
        return ['chief_complaint_summary' => 'malalamiko makuu', 'oxygen_saturation' => 'oxygen saturation'];
    }

    public function normalize(): array
    {
        return $this->only(['triage_level', 'chief_complaint_summary', 'temperature', 'systolic_bp', 'diastolic_bp', 'pulse_rate', 'respiratory_rate', 'oxygen_saturation', 'weight_kg', 'height_cm', 'blood_glucose', 'muac_cm', 'pain_score', 'consciousness_level', 'pregnancy_status', 'gestational_age_weeks', 'danger_signs', 'allergies_confirmed', 'fall_risk', 'infection_risk', 'notes']);
    }

    public function fillFromModel(TriageAssessment $assessment): void
    {
        $this->assessmentId = $assessment->id;
        $this->fill($assessment->only(array_keys($this->normalize())));
    }

    public function resetForm(): void { $this->reset(); $this->pregnancy_status = 'not_applicable'; $this->triage_level = 'routine'; }
}
