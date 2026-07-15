<?php

namespace App\Services;

use App\Enums\ClinicalAlertStatus;
use App\Models\ChildGrowthMeasurement;
use App\Models\ChildNutritionAssessment;
use App\Models\ClinicalAlert;
use App\Models\RchChild;
use Carbon\CarbonImmutable;

class ChildGrowthAssessmentService
{
    public function calculateAgeInDays(RchChild $child, string|\DateTimeInterface|null $asOf = null): int { return max(0, CarbonImmutable::parse($child->birth_date)->diffInDays(CarbonImmutable::parse($asOf ?? today()))); }
    public function calculateBmi(?float $weightKg, ?float $heightCm): ?float { return $weightKg && $heightCm ? round($weightKg / (($heightCm / 100) ** 2), 2) : null; }
    public function assessWeightForAge(ChildGrowthMeasurement $m): string { return $m->weight_kg === null ? 'indeterminate' : ((float) $m->weight_kg < 3 ? 'severely_underweight' : ((float) $m->weight_kg < 5 ? 'underweight' : 'normal')); }
    public function assessHeightForAge(ChildGrowthMeasurement $m): string { return 'indeterminate'; }
    public function assessWeightForHeight(ChildGrowthMeasurement $m): string { return 'indeterminate'; }
    public function assessBmiForAge(ChildGrowthMeasurement $m): string { return $m->bmi && (float) $m->bmi > 18 ? 'overweight' : 'indeterminate'; }
    public function assessHeadCircumferenceForAge(ChildGrowthMeasurement $m): string { return 'indeterminate'; }
    public function assessMuac(ChildGrowthMeasurement $m): string { return $m->muac_cm === null ? 'indeterminate' : ((float) $m->muac_cm < 11.5 ? 'severe_acute_malnutrition' : ((float) $m->muac_cm < 12.5 ? 'moderate_acute_malnutrition' : 'normal')); }
    public function getGrowthTrend(RchChild $child) { return $child->growthMeasurements()->latest('measured_at')->limit(12)->get()->reverse()->values(); }

    public function buildNutritionAlerts(ChildGrowthMeasurement $m, ChildNutritionAssessment $a): void
    {
        if (in_array($a->overall_nutrition_status, ['severe_acute_malnutrition', 'severely_underweight', 'severely_wasted'], true)) {
            ClinicalAlert::query()->firstOrCreate([
                'facility_id' => $m->facility_id,
                'patient_id' => $m->child_patient_id,
                'alert_type' => 'nutrition_risk',
                'source_type' => ChildNutritionAssessment::class,
                'source_id' => $a->id,
                'status' => ClinicalAlertStatus::Active,
            ], ['severity' => 'critical', 'title' => 'Critical nutrition alert', 'message' => 'Child nutrition assessment requires urgent review.']);
        }
    }
}
