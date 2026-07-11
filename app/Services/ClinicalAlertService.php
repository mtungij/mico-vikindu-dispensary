<?php

namespace App\Services;

use App\Enums\ClinicalAlertStatus;
use App\Models\ClinicalAlert;
use App\Models\TriageAssessment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClinicalAlertService
{
    public function createFromVitals(TriageAssessment $assessment, array $alerts): void
    {
        foreach ($alerts as $alert) {
            $this->preventDuplicateSourceAlerts($assessment::class, $assessment->id, $alert['title']);
            ClinicalAlert::query()->create([
                'facility_id' => $assessment->facility_id,
                'patient_id' => $assessment->patient_id,
                'visit_id' => $assessment->visit_id,
                'alert_type' => 'abnormal_vital',
                'severity' => $alert['severity'],
                'title' => $alert['title'],
                'message' => $alert['message'],
                'source_type' => $assessment::class,
                'source_id' => $assessment->id,
                'status' => ClinicalAlertStatus::Active,
            ]);
        }
    }

    public function createAllergyAlert(int $facilityId, int $patientId, ?int $visitId, string $message): ClinicalAlert
    {
        return ClinicalAlert::query()->firstOrCreate([
            'facility_id' => $facilityId,
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'alert_type' => 'allergy',
            'status' => ClinicalAlertStatus::Active,
        ], [
            'severity' => 'critical',
            'title' => 'Allergy',
            'message' => $message,
        ]);
    }

    public function acknowledge(ClinicalAlert $alert, $actor): ClinicalAlert
    {
        $alert->update(['status' => ClinicalAlertStatus::Acknowledged, 'acknowledged_by' => $actor->id, 'acknowledged_at' => now()]);
        return $alert->refresh();
    }

    public function resolve(ClinicalAlert $alert, $actor): ClinicalAlert
    {
        $alert->update(['status' => ClinicalAlertStatus::Resolved, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
        return $alert->refresh();
    }

    public function dismiss(ClinicalAlert $alert, $actor, string $reason): ClinicalAlert
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu inahitajika.']);
        }
        $alert->update(['status' => ClinicalAlertStatus::Dismissed, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
        return $alert->refresh();
    }

    public function preventDuplicateSourceAlerts(string $sourceType, int $sourceId, string $title): void
    {
        DB::table('clinical_alerts')
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('title', $title)
            ->whereIn('status', [ClinicalAlertStatus::Active->value, ClinicalAlertStatus::Acknowledged->value])
            ->delete();
    }
}
