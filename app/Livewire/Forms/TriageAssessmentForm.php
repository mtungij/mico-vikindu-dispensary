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
        return $this->draftRules();
    }

    public function draftRules(): array
    {
        return [
            'triage_level' => ['required', 'in:'.collect(TriageLevel::cases())->pluck('value')->implode(',')],
            'chief_complaint_summary' => ['nullable', 'string', 'max:2000'],
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'systolic_bp' => ['nullable', 'integer', 'between:40,280'],
            'diastolic_bp' => ['nullable', 'integer', 'between:20,180', 'lt:systolic_bp'],
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
            'allergies_confirmed' => ['boolean'],
            'fall_risk' => ['nullable', 'in:none,low,moderate,high'],
            'infection_risk' => ['nullable', 'in:none,suspected,confirmed'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function completionRules(): array
    {
        return array_replace($this->draftRules(), [
            'triage_level' => ['required', 'in:'.collect(TriageLevel::cases())->pluck('value')->implode(',')],
            'chief_complaint_summary' => ['required', 'string', 'max:2000'],
            'temperature' => ['required', 'numeric', 'between:25,45'],
            'systolic_bp' => ['required', 'integer', 'between:40,280'],
            'diastolic_bp' => ['required', 'integer', 'between:20,180', 'lt:systolic_bp'],
            'pulse_rate' => ['required', 'integer', 'between:20,250'],
            'respiratory_rate' => ['required', 'integer', 'between:4,80'],
            'oxygen_saturation' => ['required', 'numeric', 'between:0,100'],
            'pain_score' => ['required', 'integer', 'between:0,10'],
            'consciousness_level' => ['required', 'in:'.collect(ConsciousnessLevel::cases())->pluck('value')->implode(',')],
            'pregnancy_status' => ['required', 'in:'.collect(PregnancyStatus::cases())->pluck('value')->implode(',')],
            'gestational_age_weeks' => ['nullable', 'required_if:pregnancy_status,pregnant', 'integer', 'between:1,45'],
            'allergies_confirmed' => ['accepted'],
        ]);
    }

    public function validateDraft(): array
    {
        return $this->validate($this->draftRules(), $this->messages(), $this->validationAttributes());
    }

    public function validateCompletion(): array
    {
        return $this->validate($this->completionRules(), $this->messages(), $this->validationAttributes());
    }

    public function messages(): array
    {
        return [
            'triage_level.required' => 'Tafadhali chagua kiwango cha dharura cha mgonjwa.',
            'triage_level.in' => 'Kiwango cha dharura kilichochaguliwa si sahihi.',
            'chief_complaint_summary.required' => 'Tafadhali andika tatizo kuu la mgonjwa.',
            'temperature.required' => 'Joto la mwili linahitajika.',
            'temperature.numeric' => 'Joto la mwili lazima liwe namba.',
            'temperature.between' => 'Joto la mwili lazima liwe kati ya 25 na 45 °C.',
            'systolic_bp.required' => 'Shinikizo la damu la juu linahitajika.',
            'systolic_bp.integer' => 'Shinikizo la damu la juu lazima liwe namba kamili.',
            'systolic_bp.between' => 'Shinikizo la damu la juu lazima liwe kati ya 40 na 280 mmHg.',
            'diastolic_bp.required' => 'Shinikizo la damu la chini linahitajika.',
            'diastolic_bp.integer' => 'Shinikizo la damu la chini lazima liwe namba kamili.',
            'diastolic_bp.between' => 'Shinikizo la damu la chini lazima liwe kati ya 20 na 180 mmHg.',
            'diastolic_bp.lt' => 'Shinikizo la damu la chini lazima liwe chini ya shinikizo la juu.',
            'pulse_rate.required' => 'Mapigo ya moyo yanahitajika.',
            'pulse_rate.integer' => 'Mapigo ya moyo lazima yawe namba kamili.',
            'pulse_rate.between' => 'Mapigo ya moyo lazima yawe kati ya 20 na 250 kwa dakika.',
            'respiratory_rate.required' => 'Kiwango cha kupumua kinahitajika.',
            'respiratory_rate.integer' => 'Kiwango cha kupumua lazima kiwe namba kamili.',
            'respiratory_rate.between' => 'Kiwango cha kupumua lazima kiwe kati ya 4 na 80 kwa dakika.',
            'oxygen_saturation.required' => 'Kiwango cha oxygen kinahitajika.',
            'oxygen_saturation.numeric' => 'Kiwango cha oxygen lazima kiwe namba.',
            'oxygen_saturation.between' => 'Kiwango cha oxygen lazima kiwe kati ya 0 na 100%.',
            'weight_kg.numeric' => 'Uzito lazima uwe namba.',
            'weight_kg.between' => 'Uzito lazima uwe kati ya kilo 0.5 na 350.',
            'height_cm.numeric' => 'Urefu lazima uwe namba.',
            'height_cm.between' => 'Urefu lazima uwe kati ya sentimita 20 na 250.',
            'blood_glucose.numeric' => 'Kiwango cha sukari lazima kiwe namba.',
            'blood_glucose.between' => 'Kiwango cha sukari lazima kiwe kati ya 0 na 60.',
            'muac_cm.numeric' => 'MUAC lazima iwe namba.',
            'muac_cm.between' => 'MUAC lazima iwe kati ya sentimita 1 na 80.',
            'pain_score.required' => 'Kiwango cha maumivu kinahitajika.',
            'pain_score.integer' => 'Kiwango cha maumivu lazima kiwe namba kamili.',
            'pain_score.between' => 'Kiwango cha maumivu lazima kiwe kati ya 0 na 10.',
            'consciousness_level.required' => 'Tafadhali chagua kiwango cha fahamu.',
            'consciousness_level.in' => 'Kiwango cha fahamu kilichochaguliwa si sahihi.',
            'pregnancy_status.required' => 'Tafadhali chagua hali ya ujauzito.',
            'pregnancy_status.in' => 'Hali ya ujauzito iliyochaguliwa si sahihi.',
            'gestational_age_weeks.required_if' => 'Wiki za ujauzito zinahitajika kwa mgonjwa mjamzito.',
            'gestational_age_weeks.integer' => 'Wiki za ujauzito lazima ziwe namba kamili.',
            'gestational_age_weeks.between' => 'Wiki za ujauzito lazima ziwe kati ya 1 na 45.',
            'danger_signs.array' => 'Viashiria vya dharura haviko katika muundo sahihi.',
            'allergies_confirmed.accepted' => 'Thibitisha kuwa taarifa za mzio zimehakikiwa.',
            'fall_risk.in' => 'Kiwango cha hatari ya kuanguka si sahihi.',
            'infection_risk.in' => 'Kiwango cha hatari ya maambukizi si sahihi.',
            'notes.max' => 'Maelezo hayawezi kuzidi herufi 4000.',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'triage_level' => 'kiwango cha dharura',
            'chief_complaint_summary' => 'tatizo kuu la mgonjwa',
            'temperature' => 'joto la mwili',
            'systolic_bp' => 'shinikizo la damu la juu',
            'diastolic_bp' => 'shinikizo la damu la chini',
            'pulse_rate' => 'mapigo ya moyo',
            'respiratory_rate' => 'kiwango cha kupumua',
            'oxygen_saturation' => 'kiwango cha oxygen',
            'weight_kg' => 'uzito',
            'height_cm' => 'urefu',
            'blood_glucose' => 'kiwango cha sukari',
            'muac_cm' => 'MUAC',
            'pain_score' => 'kiwango cha maumivu',
            'consciousness_level' => 'kiwango cha fahamu',
            'pregnancy_status' => 'hali ya ujauzito',
            'gestational_age_weeks' => 'wiki za ujauzito',
            'danger_signs' => 'viashiria vya dharura',
            'allergies_confirmed' => 'uthibitisho wa mzio',
            'fall_risk' => 'hatari ya kuanguka',
            'infection_risk' => 'hatari ya maambukizi',
            'notes' => 'maelezo',
        ];
    }

    public function normalize(): array
    {
        return $this->only(['triage_level', 'chief_complaint_summary', 'temperature', 'systolic_bp', 'diastolic_bp', 'pulse_rate', 'respiratory_rate', 'oxygen_saturation', 'weight_kg', 'height_cm', 'blood_glucose', 'muac_cm', 'pain_score', 'consciousness_level', 'pregnancy_status', 'gestational_age_weeks', 'danger_signs', 'allergies_confirmed', 'fall_risk', 'infection_risk', 'notes']);
    }

    public function fillFromModel(TriageAssessment $assessment): void
    {
        $this->assessmentId = $assessment->id;
        $values = array_map(
            static fn ($value) => $value instanceof \BackedEnum ? $value->value : $value,
            $assessment->only(array_keys($this->normalize()))
        );
        $values['danger_signs'] = (array) ($values['danger_signs'] ?? []);
        $values['allergies_confirmed'] = (bool) ($values['allergies_confirmed'] ?? false);

        $this->fill($values);
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->pregnancy_status = 'not_applicable';
        $this->triage_level = 'routine';
    }
}
