<?php

namespace App\Services;

use App\Enums\ConsciousnessLevel;
use App\Enums\PregnancyStatus;
use App\Enums\TriageLevel;
use Illuminate\Validation\ValidationException;

class VitalSignAssessmentService
{
    public function calculateBmi(null|float|string $weightKg, null|float|string $heightCm): ?float
    {
        $weight = (float) $weightKg;
        $height = (float) $heightCm;
        if ($weight <= 0 || $height <= 0) {
            return null;
        }

        return round($weight / (($height / 100) ** 2), 2);
    }

    public function classifyBmi(?float $bmi): ?string
    {
        if ($bmi === null) {
            return null;
        }

        return match (true) {
            $bmi < 18.5 => 'underweight',
            $bmi < 25 => 'normal',
            $bmi < 30 => 'overweight',
            default => 'obese',
        };
    }

    public function validateVitalRanges(array $data): void
    {
        validator($data, [
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'systolic_bp' => ['nullable', 'integer', 'between:40,280'],
            'diastolic_bp' => ['nullable', 'integer', 'between:20,180'],
            'pulse_rate' => ['nullable', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:4,80'],
            'oxygen_saturation' => ['nullable', 'numeric', 'between:0,100'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
            'blood_glucose' => ['nullable', 'numeric', 'between:0,60'],
            'height_cm' => ['nullable', 'numeric', 'between:20,250'],
            'weight_kg' => ['nullable', 'numeric', 'between:0.5,350'],
        ])->validate();

        if (($data['systolic_bp'] ?? null) && ($data['diastolic_bp'] ?? null) && (int) $data['diastolic_bp'] >= (int) $data['systolic_bp']) {
            throw ValidationException::withMessages(['diastolic_bp' => 'Diastolic BP lazima iwe chini ya systolic BP.']);
        }
    }

    public function detectAbnormalVitals(array $data): array
    {
        $ranges = config('clinical_reference_ranges.adult');
        $alerts = [];

        if (($data['temperature'] ?? null) !== null) {
            $temp = (float) $data['temperature'];
            if ($temp >= $ranges['temperature']['critical_high']) {
                $alerts[] = ['severity' => 'high', 'title' => 'High fever', 'message' => 'Joto la mwili ni juu sana.'];
            } elseif ($temp >= $ranges['temperature']['high']) {
                $alerts[] = ['severity' => 'warning', 'title' => 'Fever', 'message' => 'Joto la mwili lipo juu ya kawaida.'];
            } elseif ($temp < $ranges['temperature']['low']) {
                $alerts[] = ['severity' => 'high', 'title' => 'Hypothermia', 'message' => 'Joto la mwili lipo chini ya kawaida.'];
            }
        }

        if (($data['systolic_bp'] ?? null) !== null || ($data['diastolic_bp'] ?? null) !== null) {
            $sys = (int) ($data['systolic_bp'] ?? 0);
            $dia = (int) ($data['diastolic_bp'] ?? 0);
            if ($sys >= $ranges['systolic_bp']['critical_high'] || $dia >= $ranges['diastolic_bp']['critical_high']) {
                $alerts[] = ['severity' => 'critical', 'title' => 'Severe hypertension', 'message' => 'BP ipo juu sana; mtoa huduma athibitishe haraka.'];
            } elseif (($sys > 0 && $sys < $ranges['systolic_bp']['low']) || ($dia > 0 && $dia < $ranges['diastolic_bp']['low'])) {
                $alerts[] = ['severity' => 'high', 'title' => 'Hypotension', 'message' => 'BP ipo chini ya kawaida.'];
            }
        }

        if (($data['pulse_rate'] ?? null) !== null) {
            $pulse = (int) $data['pulse_rate'];
            if ($pulse >= $ranges['pulse_rate']['critical_high']) {
                $alerts[] = ['severity' => 'high', 'title' => 'Tachycardia', 'message' => 'Mapigo ya moyo yapo juu sana.'];
            } elseif ($pulse < $ranges['pulse_rate']['low']) {
                $alerts[] = ['severity' => 'warning', 'title' => 'Bradycardia', 'message' => 'Mapigo ya moyo yapo chini ya kawaida.'];
            }
        }

        if (($data['oxygen_saturation'] ?? null) !== null && (float) $data['oxygen_saturation'] < $ranges['oxygen_saturation']['low']) {
            $alerts[] = ['severity' => (float) $data['oxygen_saturation'] < $ranges['oxygen_saturation']['critical_low'] ? 'critical' : 'high', 'title' => 'Low oxygen saturation', 'message' => 'Oxygen saturation ipo chini ya kiwango cha kawaida.'];
        }

        if (($data['pain_score'] ?? 0) >= 8) {
            $alerts[] = ['severity' => 'high', 'title' => 'Severe pain', 'message' => 'Pain score ni kubwa.'];
        }

        if (($data['blood_glucose'] ?? null) !== null) {
            $glucose = (float) $data['blood_glucose'];
            if ($glucose < $ranges['blood_glucose']['low']) {
                $alerts[] = ['severity' => 'high', 'title' => 'Hypoglycemia', 'message' => 'Blood glucose ipo chini.'];
            } elseif ($glucose >= $ranges['blood_glucose']['critical_high']) {
                $alerts[] = ['severity' => 'high', 'title' => 'Hyperglycemia', 'message' => 'Blood glucose ipo juu sana.'];
            }
        }

        if (in_array($data['consciousness_level'] ?? null, [ConsciousnessLevel::RespondsToPain->value, ConsciousnessLevel::Unresponsive->value, ConsciousnessLevel::Confused->value], true)) {
            $alerts[] = ['severity' => 'critical', 'title' => 'Altered consciousness', 'message' => 'Kiwango cha fahamu kinahitaji tathmini ya haraka.'];
        }

        if (in_array($data['pregnancy_status'] ?? null, [PregnancyStatus::Pregnant->value, PregnancyStatus::Suspected->value], true)
            && (($data['gestational_age_weeks'] ?? 0) >= config('clinical_reference_ranges.pregnancy.gestational_age_high_risk'))) {
            $alerts[] = ['severity' => 'warning', 'title' => 'High-risk pregnancy indicator', 'message' => 'Ujauzito unahitaji tahadhari ya kliniki.'];
        }

        return $alerts;
    }

    public function determineTriageLevelSuggestion(array $data): TriageLevel
    {
        $alerts = $this->detectAbnormalVitals($data);
        if (collect($alerts)->contains(fn ($alert) => $alert['severity'] === 'critical') || ! empty($data['danger_signs'])) {
            return TriageLevel::Emergency;
        }
        if (collect($alerts)->contains(fn ($alert) => $alert['severity'] === 'high')) {
            return TriageLevel::Urgent;
        }
        if (collect($alerts)->contains(fn ($alert) => $alert['severity'] === 'warning')) {
            return TriageLevel::Priority;
        }

        return TriageLevel::Routine;
    }

    public function buildClinicalAlerts(array $data): array
    {
        return $this->detectAbnormalVitals($data);
    }

    public function assessAdultVitals(array $data): array { return $this->detectAbnormalVitals($data); }
    public function assessPediatricVitals(array $data): array { return $this->detectAbnormalVitals($data); }
    public function assessPregnancyVitals(array $data): array { return $this->detectAbnormalVitals($data); }
}
