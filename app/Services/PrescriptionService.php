<?php

namespace App\Services;

use App\Enums\PrescriptionStatus;
use App\Models\ActivityLog;
use App\Models\ClinicalEncounter;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PrescriptionService
{
    public function __construct(
        private readonly SequenceNumberService $numbers,
        private readonly VisitClosureService $visitClosure,
        private readonly PrescriptionBillingService $billing,
    ) {}

    public function generatePrescriptionNumber(int $facilityId): string
    {
        return $this->numbers->next('prescription_number_sequences', $facilityId, 'RX', 6);
    }

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
        $prescription->items()->create([...$this->withCalculatedQuantity($data), 'status' => 'prescribed', 'created_by' => $actor->id]);
    }

    public function updateItem(PrescriptionItem $item, array $data, $actor): PrescriptionItem
    {
        return DB::transaction(function () use ($item, $data, $actor): PrescriptionItem {
            $item = PrescriptionItem::query()->with('prescription')->lockForUpdate()->findOrFail($item->id);
            $this->authorizeDraftMutation($item->prescription, $actor);
            validator($data, ['medication_name' => ['required'], 'dose' => ['required'], 'frequency' => ['required'], 'duration_value' => ['required', 'integer', 'min:1'], 'duration_unit' => ['required']])->validate();
            $old = $item->only(array_keys($data));
            $item->update([...$this->withCalculatedQuantity($data), 'updated_by' => $actor->id]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'prescription_item_updated', 'subject_type' => $item::class, 'subject_id' => $item->id, 'old_values' => $old, 'new_values' => $item->fresh()->only(array_keys($data))]);

            return $item->refresh();
        });
    }

    public function assertItemEditable(PrescriptionItem $item, $actor): void
    {
        $item->loadMissing('prescription.encounter.visit');
        $this->authorizeDraftMutation($item->prescription, $actor);
    }

    public function removeItem(PrescriptionItem $item, $actor): void
    {
        DB::transaction(function () use ($item, $actor): void {
            $item = PrescriptionItem::query()->with('prescription')->lockForUpdate()->findOrFail($item->id);
            $this->authorizeDraftMutation($item->prescription, $actor);
            $old = $item->toArray();
            $item->delete();
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'prescription_item_removed', 'subject_type' => $item::class, 'subject_id' => $item->id, 'old_values' => $old, 'new_values' => []]);
        });
    }

    public function updateDraft(Prescription $prescription, array $data, $actor): Prescription
    {
        $this->authorizeDraftMutation($prescription, $actor);
        $prescription->update([...$data, 'updated_by' => $actor->id]);

        return $prescription->refresh();
    }

    private function authorizeDraftMutation(Prescription $prescription, $actor): void
    {
        Gate::forUser($actor)->authorize('update', $prescription);
        if ($prescription->facility_id !== currentFacility()?->id || ! $actor->belongsToCurrentFacility()) {
            abort(403);
        }
        $prescription->loadMissing('encounter.visit');
        if ($prescription->encounter?->isReadOnly()) {
            throw ValidationException::withMessages(['prescription' => 'Consultation hii tayari imekamilika.']);
        }
        if (in_array($prescription->status, [PrescriptionStatus::PartiallyDispensed, PrescriptionStatus::Dispensed], true)
            || $prescription->items()->where('dispensed_quantity', '>', 0)->exists()
            || $prescription->dispensings()->exists()) {
            throw ValidationException::withMessages(['prescription' => 'Dawa hii tayari imelipiwa au kutolewa.']);
        }
        if (! $prescription->isEditableDraft()) {
            throw ValidationException::withMessages(['prescription' => 'Dawa hii haiwezi kuhaririwa kwa sababu prescription tayari imetumwa Billing au Pharmacy.']);
        }
    }

    private function withCalculatedQuantity(array $data): array
    {
        $frequency = strtoupper(trim((string) ($data['frequency'] ?? '')));
        $perDay = ['OD' => 1, 'DAILY' => 1, 'BD' => 2, 'BID' => 2, 'TDS' => 3, 'TID' => 3, 'QID' => 4][$frequency] ?? null;
        if ($perDay && preg_match('/^\s*(\d+(?:\.\d+)?)/', (string) ($data['dose'] ?? ''), $match)) {
            $days = (int) ($data['duration_value'] ?? 0) * match ($data['duration_unit'] ?? 'days') {
                'weeks' => 7, 'months' => 30, default => 1,
            };
            $data['quantity'] = (float) $match[1] * $perDay * $days;
        }

        return $data;
    }

    public function finalizePrescription(Prescription $prescription, $actor): Prescription
    {
        if (! $prescription->items()->exists()) {
            throw ValidationException::withMessages(['items' => 'Prescription lazima iwe na dawa angalau moja.']);
        }
        $prescription->update(['status' => PrescriptionStatus::Prescribed, 'updated_by' => $actor->id]);

        return $this->billing->bill($prescription->refresh(), $actor);
    }

    public function cancelPrescription(Prescription $prescription, string $reason, $actor): Prescription
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu ya kufuta prescription inahitajika.']);
        }
        $prescription->update(['status' => PrescriptionStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $reason, 'updated_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'prescription_cancelled', 'subject_type' => $prescription::class, 'subject_id' => $prescription->id]);
        if (! Prescription::query()
            ->where('visit_id', $prescription->visit_id)
            ->whereKeyNot($prescription->id)
            ->whereIn('status', [
                PrescriptionStatus::Draft->value,
                PrescriptionStatus::Prescribed->value,
                PrescriptionStatus::AwaitingPayment->value,
                PrescriptionStatus::PartiallyDispensed->value,
            ])->exists()) {
            $this->visitClosure->cancelDepartmentQueues($prescription->visit, 'PHA', $actor, $reason);
        }
        $this->visitClosure->evaluate($prescription->visit->refresh(), $actor);

        return $prescription->refresh();
    }
}
