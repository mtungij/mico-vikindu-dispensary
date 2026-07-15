<?php

namespace App\Services;

use App\Enums\ClinicalAlertStatus;
use App\Models\AncVisit;
use App\Models\ClinicalAlert;
use App\Models\Pregnancy;
use App\Models\PregnancyRiskFactor;
use App\Models\PregnancyRiskFactorType;
use Illuminate\Support\Facades\DB;

class PregnancyRiskAssessmentService
{
    public function assessPregnancy(Pregnancy $pregnancy, ?AncVisit $visit, array $signals, $actor): array
    {
        return DB::transaction(function () use ($pregnancy, $visit, $signals, $actor): array {
            $risks = $this->detectRiskFactors($signals);
            foreach ($risks as $type) {
                PregnancyRiskFactor::query()->firstOrCreate([
                    'facility_id' => $pregnancy->facility_id,
                    'pregnancy_id' => $pregnancy->id,
                    'risk_factor_type_id' => $type->id,
                    'status' => 'active',
                ], [
                    'anc_visit_id' => $visit?->id,
                    'severity' => $type->severity,
                    'details' => $signals['details'][$type->code] ?? null,
                    'detected_by' => $actor->id,
                    'detected_at' => now(),
                ]);
            }

            $level = $this->calculateRiskLevel($pregnancy);
            $pregnancy->update(['high_risk' => $level !== 'low', 'risk_level' => $level, 'updated_by' => $actor->id]);
            if ($level !== 'low') {
                $this->createClinicalAlerts($pregnancy, $level);
            }
            return ['level' => $level, 'risks' => $risks];
        });
    }

    public function detectRiskFactors(array $signals): array
    {
        $codes = collect($signals['codes'] ?? [])->filter()->values();
        if (($signals['systolic_bp'] ?? 0) >= 140 || ($signals['diastolic_bp'] ?? 0) >= 90) {
            $codes->push('hypertension');
        }
        if (($signals['hemoglobin'] ?? 99) < 8) {
            $codes->push('severe_anemia');
        }
        return PregnancyRiskFactorType::query()->whereIn('code', $codes->unique()->all())->get()->all();
    }

    public function calculateRiskLevel(Pregnancy $pregnancy): string
    {
        $severities = $this->getActiveRisks($pregnancy)->pluck('severity');
        return $severities->contains('critical') ? 'critical' : ($severities->contains('high') ? 'high' : ($severities->contains('moderate') ? 'moderate' : 'low'));
    }

    public function createClinicalAlerts(Pregnancy $pregnancy, string $level): void
    {
        ClinicalAlert::query()->firstOrCreate([
            'facility_id' => $pregnancy->facility_id,
            'patient_id' => $pregnancy->patient_id,
            'alert_type' => 'pregnancy_risk',
            'source_type' => Pregnancy::class,
            'source_id' => $pregnancy->id,
            'status' => ClinicalAlertStatus::Active,
        ], [
            'severity' => $level === 'critical' ? 'critical' : 'high',
            'title' => 'High-risk pregnancy',
            'message' => "Pregnancy {$pregnancy->pregnancy_number} is classified as {$level} risk.",
        ]);
    }

    public function recommendReferral(Pregnancy $pregnancy): bool { return $this->getActiveRisks($pregnancy)->contains(fn ($risk) => $risk->type?->referral_recommended); }
    public function acknowledgeRisk(PregnancyRiskFactor $risk, $actor): PregnancyRiskFactor { $risk->update(['status' => 'acknowledged', 'acknowledged_by' => $actor->id, 'acknowledged_at' => now()]); return $risk->refresh(); }
    public function resolveRiskFactor(PregnancyRiskFactor $risk, $actor): PregnancyRiskFactor { $risk->update(['status' => 'resolved', 'resolved_by' => $actor->id, 'resolved_at' => now()]); return $risk->refresh(); }
    public function getActiveRisks(Pregnancy $pregnancy) { return $pregnancy->riskFactors()->with('type')->whereIn('status', ['active', 'acknowledged'])->get(); }
}
