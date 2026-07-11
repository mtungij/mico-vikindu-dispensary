<?php

namespace App\Services;

use App\Enums\ClinicalAlertStatus;
use App\Enums\LaboratoryCriticalNotificationStatus;
use App\Models\ActivityLog;
use App\Models\ClinicalAlert;
use App\Models\LaboratoryCriticalResultNotification;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryResultValue;

class LaboratoryCriticalResultService
{
    public function createAlert(LaboratoryResult $result, LaboratoryResultValue $value, $actor): ClinicalAlert
    {
        $message = "{$result->test->name} {$value->parameter_name_snapshot}: ".($value->numeric_value ?? $value->selected_value ?? $value->text_value).' (critical)';
        $alert = ClinicalAlert::query()->firstOrCreate([
            'facility_id' => $result->facility_id,
            'patient_id' => $result->order->patient_id,
            'visit_id' => $result->order->visit_id,
            'alert_type' => 'laboratory_critical_result',
            'source_type' => $value::class,
            'source_id' => $value->id,
            'status' => ClinicalAlertStatus::Active,
        ], [
            'clinical_encounter_id' => $result->order->clinical_encounter_id,
            'severity' => 'critical',
            'title' => 'Critical laboratory result',
            'message' => $message,
        ]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'critical_result_detected', 'subject_type' => $result::class, 'subject_id' => $result->id]);
        LaboratoryCriticalResultNotification::query()->firstOrCreate([
            'laboratory_result_id' => $result->id,
            'laboratory_result_value_id' => $value->id,
            'status' => LaboratoryCriticalNotificationStatus::Pending,
        ], [
            'facility_id' => $result->facility_id,
            'notification_method' => 'system',
            'notified_by' => $actor->id,
            'notified_at' => now(),
            'communication_notes' => 'Pending clinician notification.',
        ]);

        return $alert;
    }

    public function notify(LaboratoryResult $result, ?LaboratoryResultValue $value, array $data, $actor): LaboratoryCriticalResultNotification
    {
        $notification = LaboratoryCriticalResultNotification::query()->create([
            'facility_id' => $result->facility_id,
            'laboratory_result_id' => $result->id,
            'laboratory_result_value_id' => $value?->id,
            'notified_to_user_id' => $data['notified_to_user_id'] ?? null,
            'notification_method' => $data['notification_method'] ?? 'system',
            'notified_by' => $actor->id,
            'notified_at' => now(),
            'communication_notes' => $data['communication_notes'] ?? null,
            'status' => LaboratoryCriticalNotificationStatus::Notified,
        ]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'critical_result_notified', 'subject_type' => $result::class, 'subject_id' => $result->id]);
        return $notification;
    }

    public function acknowledge(LaboratoryCriticalResultNotification $notification, $actor): LaboratoryCriticalResultNotification
    {
        $notification->update(['status' => LaboratoryCriticalNotificationStatus::Acknowledged, 'acknowledged_by' => $actor->id, 'acknowledged_at' => now()]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'critical_result_acknowledged', 'subject_type' => $notification::class, 'subject_id' => $notification->id]);
        return $notification->refresh();
    }
}
