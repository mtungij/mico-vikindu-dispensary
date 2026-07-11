<?php

namespace App\Services;

use App\Enums\PrescriptionStatus;
use App\Models\ActivityLog;
use App\Models\ClinicalEncounter;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrescriptionService
{
    public function __construct(private readonly SequenceNumberService $numbers) {}
    public function generatePrescriptionNumber(int $facilityId): string { return $this->numbers->next('prescription_number_sequences', $facilityId, 'RX', 6); }

    public function createPrescription(ClinicalEncounter $encounter, array $data, $actor): Prescription
    {
        return DB::transaction(function () use ($encounter, $data, $actor) {
            $prescription = Prescription::query()->create([
                'facility_id' => $encounter->facility_id,
                'patient_id' => $encounter->patient_id,
                'visit_id' => $encounter->visit_id,
                'clinical_encounter_id' => $encounter->id,
                'prescribed_by' => $actor->id,
                'prescription_number' => $this->generatePrescriptionNumber($encounter->facility_id),
                'status' => PrescriptionStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'prescribed_at' => now(),
                'created_by' => $actor->id,
            ]);
            foreach ($data['items'] ?? [] as $item) {
                $this->addItem($prescription, $item, $actor);
            }
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'prescription_created', 'subject_type' => $prescription::class, 'subject_id' => $prescription->id]);
            return $prescription->refresh();
        });
    }

    public function addItem(Prescription $prescription, array $data, $actor): void
    {
        validator($data, ['medication_name' => ['required'], 'dose' => ['required'], 'frequency' => ['required'], 'duration_value' => ['required', 'integer', 'min:1'], 'duration_unit' => ['required']])->validate();
        $prescription->items()->create([...$data, 'status' => 'prescribed', 'created_by' => $actor->id]);
    }

    public function updateDraft(Prescription $prescription, array $data, $actor): Prescription
    {
        if ($prescription->status !== PrescriptionStatus::Draft) {
            throw ValidationException::withMessages(['prescription' => 'Prescription si draft.']);
        }
        $prescription->update([...$data, 'updated_by' => $actor->id]);
        return $prescription->refresh();
    }

    public function finalizePrescription(Prescription $prescription, $actor): Prescription
    {
        if (! $prescription->items()->exists()) {
            throw ValidationException::withMessages(['items' => 'Prescription lazima iwe na dawa angalau moja.']);
        }
        $prescription->update(['status' => PrescriptionStatus::Prescribed, 'updated_by' => $actor->id]);
        return $prescription->refresh();
    }

    public function cancelPrescription(Prescription $prescription, string $reason, $actor): Prescription
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu ya kufuta prescription inahitajika.']);
        }
        $prescription->update(['status' => PrescriptionStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $reason, 'updated_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'prescription_cancelled', 'subject_type' => $prescription::class, 'subject_id' => $prescription->id]);
        return $prescription->refresh();
    }
}
