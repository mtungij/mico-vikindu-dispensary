<?php

namespace App\Services;

use App\Enums\PrescriptionStatus;
use App\Models\ActivityLog;
use App\Models\ClinicalEncounter;
use App\Models\Medicine;
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
        private readonly MedicineBillingReadinessService $billingReadiness,
    ) {}

    public function generatePrescriptionNumber(int $facilityId): string
    {
        return $this->numbers->next('prescription_number_sequences', $facilityId, 'RX', 6);
    }

    public function createPrescription(ClinicalEncounter $encounter, array $data, $actor): Prescription
    {
        validator($data, ['items' => ['required', 'array', 'min:1']])->validate();

        return DB::transaction(function () use ($encounter, $data, $actor) {
            $encounter = ClinicalEncounter::query()->lockForUpdate()->findOrFail($encounter->id);
            Gate::forUser($actor)->authorize('create', Prescription::class);
            abort_unless($encounter->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);
            if ($encounter->isReadOnly()) {
                throw ValidationException::withMessages(['encounter' => 'Consultation hii tayari imekamilika na haiwezi kuongezewa dawa.']);
            }
            $prescription = Prescription::query()
                ->where('clinical_encounter_id', $encounter->id)
                ->where('status', PrescriptionStatus::Draft->value)
                ->whereDoesntHave('items', fn ($query) => $query->withTrashed()
                    ->whereNotNull('invoice_item_id')
                    ->orWhere('dispensed_quantity', '>', 0))
                ->whereDoesntHave('dispensings')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $prescription) {
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
                ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'prescription_created', 'subject_type' => $prescription::class, 'subject_id' => $prescription->id]);
            } elseif (array_key_exists('notes', $data)) {
                $prescription->update(['notes' => $data['notes'], 'updated_by' => $actor->id]);
            }

            foreach ($data['items'] ?? [] as $item) {
                $this->addItem($prescription, $item, $actor);
            }

            return $prescription->refresh();
        });
    }

    public function addItem(Prescription $prescription, array $data, $actor): void
    {
        $this->authorizeDraftMutation($prescription, $actor);
        $data = $this->prepareMedicineData($prescription, $data);
        $data = $this->withCalculatedQuantity($data);
        validator($data, $this->itemRules(), $this->itemMessages())->validate();
        $prescription->items()->create([...$data, 'status' => 'prescribed', 'created_by' => $actor->id]);
    }

    public function updateItem(PrescriptionItem $item, array $data, $actor): PrescriptionItem
    {
        return DB::transaction(function () use ($item, $data, $actor): PrescriptionItem {
            $item = PrescriptionItem::query()->with('prescription')->lockForUpdate()->findOrFail($item->id);
            $this->authorizeDraftMutation($item->prescription, $actor);
            $data = $this->prepareMedicineData($item->prescription, $data);
            $data = $this->withCalculatedQuantity($data);
            validator($data, $this->itemRules(), $this->itemMessages())->validate();
            $old = $item->only(array_keys($data));
            $item->update([...$data, 'updated_by' => $actor->id]);
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
            $this->deleteEmptyDraftIfSafe($item->prescription, $actor);
        });
    }

    public function deleteEmptyDraftIfSafe(Prescription $prescription, $actor): bool
    {
        $prescription = Prescription::query()->lockForUpdate()->find($prescription->id);
        if (! $prescription
            || $prescription->status !== PrescriptionStatus::Draft
            || $prescription->items()->exists()
            || $prescription->items()->withTrashed()->where(fn ($query) => $query
                ->whereNotNull('invoice_item_id')
                ->orWhere('dispensed_quantity', '>', 0))->exists()
            || $prescription->dispensings()->exists()) {
            return false;
        }

        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => 'empty_draft_prescription_removed',
            'subject_type' => $prescription::class,
            'subject_id' => $prescription->id,
            'old_values' => ['status' => PrescriptionStatus::Draft->value, 'clinical_encounter_id' => $prescription->clinical_encounter_id],
            'new_values' => [],
        ]);
        $prescription->delete();

        return true;
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
        if (array_key_exists('quantity', $data)) {
            return $data;
        }

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

    private function prepareMedicineData(Prescription $prescription, array $data): array
    {
        if (empty($data['medicine_id'])) {
            return $data;
        }

        $prescription->loadMissing('visit.invoice.patientPayerProfile', 'visit.payerProfile');
        $medicine = Medicine::withTrashed()
            ->with(['service', 'generic', 'dosageForm', 'route'])
            ->where('facility_id', $prescription->facility_id)
            ->find($data['medicine_id']);
        if (! $medicine) {
            throw ValidationException::withMessages([
                'medicine_id' => 'Selected medicine is not available at this facility. Contact Pharmacy/Administrator.',
            ]);
        }

        $this->billingReadiness->assertReady($medicine, $prescription->visit);

        return [
            ...$data,
            'medicine_id' => $medicine->id,
            'service_id' => $medicine->service_id,
            'medication_name' => $medicine->name,
            'generic_name' => $medicine->generic?->name,
            'strength' => $medicine->strength,
            'dosage_form' => $medicine->dosageForm?->name,
            'route' => $data['route'] ?? $medicine->route?->name,
        ];
    }

    private function itemRules(): array
    {
        return [
            'medicine_id' => ['required', 'integer'],
            'medication_name' => ['required'],
            'dose' => ['required'],
            'frequency' => ['required'],
            'duration_value' => ['required', 'integer', 'min:1'],
            'duration_unit' => ['required'],
            'quantity' => ['required', 'numeric', 'min:1'],
        ];
    }

    private function itemMessages(): array
    {
        return [
            'quantity.required' => 'Weka kiasi cha dawa. Kiasi lazima kiwe angalau 1.',
            'quantity.numeric' => 'Weka kiasi cha dawa. Kiasi lazima kiwe angalau 1.',
            'quantity.min' => 'Weka kiasi cha dawa. Kiasi lazima kiwe angalau 1.',
        ];
    }

    public function finalizePrescription(Prescription $prescription, $actor): Prescription
    {
        if (! $prescription->items()->whereNull('terminal_status')->whereNotIn('status', ['cancelled', 'declined', 'unavailable', 'substituted_elsewhere'])->exists()) {
            throw ValidationException::withMessages(['items' => 'Prescription lazima iwe na dawa angalau moja.']);
        }
        $prescription->update(['status' => PrescriptionStatus::Prescribed, 'updated_by' => $actor->id]);

        $prescription = $this->billing->bill($prescription->refresh(), $actor);
        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => $prescription->status === PrescriptionStatus::AwaitingPayment ? 'prescription_awaiting_payment' : 'prescription_finalized',
            'subject_type' => $prescription::class,
            'subject_id' => $prescription->id,
            'new_values' => ['facility_id' => $prescription->facility_id, 'patient_id' => $prescription->patient_id, 'visit_id' => $prescription->visit_id],
        ]);

        return $prescription;
    }

    public function cancelPrescription(Prescription $prescription, string $reason, $actor): Prescription
    {
        abort_unless($prescription->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu ya kufuta prescription inahitajika.']);
        }
        if ($prescription->items()->where('dispensed_quantity', '>', 0)->exists()) {
            throw ValidationException::withMessages(['prescription' => 'A partially dispensed prescription cannot be cancelled; decline only the unfilled remainder.']);
        }
        $prescription->items()->with('invoiceItem')->get()->each(function (PrescriptionItem $item) use ($actor, $reason): void {
            if ($item->invoiceItem && ! in_array($item->invoiceItem->status, ['cancelled', 'reversed'], true)) {
                app(BillingChargeService::class)->cancelCharge($item->invoiceItem, $actor, $reason);
            }
            $item->update(['status' => 'cancelled', 'terminal_status' => 'cancelled', 'terminal_reason' => $reason, 'terminal_at' => now(), 'terminal_by' => $actor->id, 'remaining_quantity' => 0]);
        });
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

    public function terminallyDeclineItem(PrescriptionItem $item, string $status, string $reason, $actor): PrescriptionItem
    {
        if (! in_array($status, ['declined', 'unavailable', 'substituted_elsewhere'], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid terminal medicine status.']);
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        return DB::transaction(function () use ($item, $status, $reason, $actor): PrescriptionItem {
            $item = PrescriptionItem::query()->with(['prescription.visit', 'invoiceItem'])->lockForUpdate()->findOrFail($item->id);
            abort_unless(
                $item->prescription->facility_id === currentFacility()?->id
                && $actor->belongsToCurrentFacility()
                && $actor->can('pharmacy.dispense'),
                403
            );
            if ($item->terminal_status || (float) $item->remaining_quantity <= 0) {
                throw ValidationException::withMessages(['item' => 'This medicine item is already terminal.']);
            }
            if ($item->invoiceItem) {
                app(BillingChargeService::class)->adjustChargeQuantity($item->invoiceItem, (float) $item->dispensed_quantity, $actor, $reason);
            }
            $item->update([
                'status' => $status,
                'dispensing_status' => $status,
                'terminal_status' => $status,
                'terminal_reason' => $reason,
                'terminal_at' => now(),
                'terminal_by' => $actor->id,
                'remaining_quantity' => 0,
                'updated_by' => $actor->id,
            ]);
            $prescription = $item->prescription;
            $active = $prescription->items()->whereNull('terminal_status')->whereColumn('dispensed_quantity', '<', 'quantity')->exists();
            if (! $active) {
                $allDeclined = ! $prescription->items()->where('dispensed_quantity', '>', 0)->exists();
                $prescription->update([
                    'status' => $allDeclined ? PrescriptionStatus::Cancelled : PrescriptionStatus::Dispensed,
                    'dispensed_at' => $allDeclined ? null : now(),
                    'cancellation_reason' => $allDeclined ? $reason : null,
                    'cancelled_at' => $allDeclined ? now() : null,
                    'updated_by' => $actor->id,
                ]);
                $this->visitClosure->completeDepartmentQueues($prescription->visit, 'PHA', $actor);
            } else {
                $prescription->update(['status' => PrescriptionStatus::PartiallyDispensed, 'updated_by' => $actor->id]);
            }
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'medicine_terminally_declined', 'subject_type' => $item::class, 'subject_id' => $item->id, 'new_values' => ['status' => $status, 'reason' => $reason, 'visit_id' => $prescription->visit_id]]);
            $this->visitClosure->evaluate($prescription->visit->refresh(), $actor);

            return $item->refresh();
        });
    }
}
