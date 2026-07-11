<?php

namespace App\Services;

use App\Enums\ReferralStatus;
use App\Enums\ReferralType;
use App\Models\ActivityLog;
use App\Models\ClinicalEncounter;
use App\Models\PatientReferral;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    public function __construct(private readonly SequenceNumberService $numbers) {}
    public function generateReferralNumber(int $facilityId): string { return $this->numbers->next('referral_number_sequences', $facilityId, 'REF', 6); }

    public function createReferral(ClinicalEncounter $encounter, array $data, $actor): PatientReferral
    {
        return DB::transaction(function () use ($encounter, $data, $actor) {
            validator($data, ['destination_facility_name' => ['required'], 'reason' => ['required'], 'urgency' => ['required']])->validate();
            $latestTriage = $encounter->visit->latestTriageAssessment;
            $referral = PatientReferral::query()->create([
                'facility_id' => $encounter->facility_id,
                'patient_id' => $encounter->patient_id,
                'visit_id' => $encounter->visit_id,
                'clinical_encounter_id' => $encounter->id,
                'referral_number' => $this->generateReferralNumber($encounter->facility_id),
                'referral_type' => $data['referral_type'] ?? ReferralType::External,
                'destination_facility_name' => $data['destination_facility_name'],
                'destination_department' => $data['destination_department'] ?? null,
                'destination_contact' => $data['destination_contact'] ?? null,
                'reason' => $data['reason'],
                'provisional_diagnosis' => $data['provisional_diagnosis'] ?? null,
                'clinical_summary' => $data['clinical_summary'] ?? $encounter->clinical_summary,
                'treatment_given' => $data['treatment_given'] ?? $encounter->treatment_plan,
                'investigations_done' => $data['investigations_done'] ?? null,
                'current_medications' => $data['current_medications'] ?? null,
                'vital_signs_snapshot' => $latestTriage?->only(['temperature', 'systolic_bp', 'diastolic_bp', 'pulse_rate', 'respiratory_rate', 'oxygen_saturation', 'triage_level']),
                'urgency' => $data['urgency'],
                'transport_method' => $data['transport_method'] ?? null,
                'accompanying_person' => $data['accompanying_person'] ?? null,
                'referred_by' => $actor->id,
                'referred_at' => now(),
                'status' => ReferralStatus::Prepared,
                'created_by' => $actor->id,
            ]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'referral_created', 'subject_type' => $referral::class, 'subject_id' => $referral->id]);
            return $referral;
        });
    }

    public function cancelReferral(PatientReferral $referral, string $reason, $actor): PatientReferral
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu inahitajika.']);
        }
        $referral->update(['status' => ReferralStatus::Cancelled, 'updated_by' => $actor->id, 'feedback_notes' => trim(($referral->feedback_notes ? $referral->feedback_notes."\n" : '').'Cancelled: '.$reason)]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'referral_cancelled', 'subject_type' => $referral::class, 'subject_id' => $referral->id]);
        return $referral->refresh();
    }
}
