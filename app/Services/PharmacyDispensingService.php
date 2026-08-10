<?php

namespace App\Services;

use App\Enums\DispensingStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\StockMovementType;
use App\Models\ActivityLog;
use App\Models\Dispensing;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PharmacyDispensingService
{
    public function __construct(
        private readonly DispensingNumberService $numbers,
        private readonly PharmacyBatchAllocationService $allocator,
        private readonly StockMovementService $movements,
        private readonly PharmacyPricingService $pricing,
        private readonly VisitClosureService $visitClosure,
        private readonly PrescriptionBillingService $billing,
    ) {}

    public function dispense(Prescription $prescription, array $lines, StockLocation $location, $actor, ?string $overrideReason = null): Dispensing
    {
        return DB::transaction(function () use ($prescription, $lines, $location, $actor, $overrideReason) {
            $prescription = Prescription::query()->with(['items', 'visit.invoice'])->lockForUpdate()->findOrFail($prescription->id);
            abort_unless($actor->can('pharmacy.dispense') && $actor->belongsToCurrentFacility(), 403);
            $this->validatePrescription($prescription, $actor, $overrideReason);
            abort_unless($location->facility_id === $prescription->facility_id && $location->is_dispensing_location, 422);

            $dispensing = Dispensing::query()->create([
                'facility_id' => $prescription->facility_id,
                'prescription_id' => $prescription->id,
                'patient_id' => $prescription->patient_id,
                'visit_id' => $prescription->visit_id,
                'dispensing_number' => $this->numbers->next($prescription->facility_id),
                'stock_location_id' => $location->id,
                'status' => DispensingStatus::InProgress,
                'payment_status' => $prescription->visit->invoice?->invoice_status?->value ?? 'not_required',
                'dispensed_by' => $actor->id,
                'dispensed_at' => now(),
                'notes' => $overrideReason,
                'created_by' => $actor->id,
            ]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'dispensing_started', 'subject_type' => $dispensing::class, 'subject_id' => $dispensing->id]);

            foreach ($lines as $line) {
                $item = $prescription->items()->lockForUpdate()->findOrFail($line['prescription_item_id']);
                $medicine = Medicine::query()->where('facility_id', $prescription->facility_id)->findOrFail($line['medicine_id'] ?? $item->medicine_id);
                if (! $medicine->is_active) {
                    throw ValidationException::withMessages(['medicine' => "{$medicine->name} is inactive and cannot be dispensed."]);
                }
                $quantity = (float) ($line['quantity'] ?? $item->remaining_quantity ?? $item->quantity ?? 0);
                $remaining = (float) ($item->remaining_quantity ?? $item->quantity ?? 0);
                if ($quantity <= 0) {
                    throw ValidationException::withMessages(['quantity' => 'Dispensing quantity must be greater than zero.']);
                }
                if ($quantity > $remaining) {
                    throw ValidationException::withMessages(['quantity' => 'Quantity imezidi kiasi kilichobaki.']);
                }
                $substitutionFrom = null;
                if ($item->medicine_id && $item->medicine_id !== $medicine->id) {
                    if (! $item->substitution_allowed) {
                        throw ValidationException::withMessages(['substitution' => 'Substitution hairuhusiwi kwa item hii.']);
                    }
                    if (! $actor->can('pharmacy.substitute-equivalent')) {
                        throw ValidationException::withMessages(['substitution' => 'Huna ruhusa ya substitution.']);
                    }
                    $substitutionFrom = $item->medicine_id;
                    ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'medicine_substituted', 'subject_type' => $item::class, 'subject_id' => $item->id]);
                }

                $price = $this->pricing->calculateDispensingAmount($medicine, $prescription, (string) $quantity);
                $dispensingItem = $dispensing->items()->create([
                    'prescription_item_id' => $item->id,
                    'medicine_id' => $medicine->id,
                    'prescribed_quantity' => $item->quantity ?? $quantity,
                    'dispensed_quantity' => $quantity,
                    'unit_price_snapshot' => $price['unit_price'],
                    'total_amount' => $price['total'],
                    'patient_amount' => $price['patient_amount'],
                    'insurance_amount' => $price['insurance_amount'],
                    'payer_amount' => $price['payer_amount'],
                    'substitution_from_medicine_id' => $substitutionFrom,
                    'substitution_reason' => $line['substitution_reason'] ?? null,
                    'instructions_snapshot' => $item->instructions,
                    'status' => $quantity < $remaining ? 'partially_dispensed' : 'dispensed',
                    'created_by' => $actor->id,
                ]);

                foreach ($this->allocator->allocateFefo($medicine, $location, (string) $quantity) as $allocation) {
                    $batch = $allocation['batch'];
                    $allocated = (string) $allocation['quantity'];
                    if (! $dispensingItem->medicine_batch_id) {
                        $dispensingItem->update(['medicine_batch_id' => $batch->id]);
                    }
                    $dispensingItem->allocations()->create(['medicine_batch_id' => $batch->id, 'quantity' => $allocated, 'unit_cost_snapshot' => $batch->unit_cost, 'expiry_date_snapshot' => $batch->expiry_date]);
                    $this->movements->stockOut($batch, StockMovementType::Dispensing, $allocated, $actor, $dispensingItem, 'Medicine dispensed');
                }

                $newDispensed = (float) $item->dispensed_quantity + $quantity;
                $newRemaining = max(0, (float) ($item->quantity ?? $newDispensed) - $newDispensed);
                $item->update([
                    'medicine_id' => $item->medicine_id ?: $medicine->id,
                    'dispensed_quantity' => $newDispensed,
                    'remaining_quantity' => $newRemaining,
                    'substitution_medicine_id' => $substitutionFrom ? $medicine->id : $item->substitution_medicine_id,
                    'substitution_reason' => $line['substitution_reason'] ?? $item->substitution_reason,
                    'dispensing_status' => $newRemaining > 0 ? 'partially_dispensed' : 'dispensed',
                    'unit_price_snapshot' => $price['unit_price'],
                    'patient_amount' => $price['patient_amount'],
                    'insurance_amount' => $price['insurance_amount'],
                    'payer_amount' => $price['payer_amount'],
                    'status' => $newRemaining > 0 ? 'partially_dispensed' : 'dispensed',
                ]);
                ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'medicine_dispensed', 'subject_type' => $dispensingItem::class, 'subject_id' => $dispensingItem->id]);
            }

            $remainingItems = $prescription->items()
                ->whereNull('terminal_status')
                ->where(function ($query) {
                    $query->whereNull('quantity')->orWhereColumn('dispensed_quantity', '<', 'quantity');
                })->count();
            $status = $remainingItems > 0 ? PrescriptionStatus::PartiallyDispensed : PrescriptionStatus::Dispensed;
            $prescription->update(['status' => $status, 'dispensed_at' => $status === PrescriptionStatus::Dispensed ? now() : null, 'updated_by' => $actor->id]);
            $dispensing->update(['status' => $status === PrescriptionStatus::Dispensed ? DispensingStatus::Completed : DispensingStatus::PartiallyDispensed]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => $status === PrescriptionStatus::Dispensed ? 'medicine_dispensed' : 'partial_dispensing_completed', 'subject_type' => $prescription::class, 'subject_id' => $prescription->id]);

            if ($status === PrescriptionStatus::Dispensed) {
                $otherActivePrescriptions = Prescription::query()
                    ->where('visit_id', $prescription->visit_id)
                    ->whereKeyNot($prescription->id)
                    ->whereIn('status', [
                        PrescriptionStatus::Draft->value,
                        PrescriptionStatus::Prescribed->value,
                        PrescriptionStatus::AwaitingPayment->value,
                        PrescriptionStatus::PartiallyDispensed->value,
                    ])
                    ->exists();
                if (! $otherActivePrescriptions) {
                    $this->visitClosure->completeDepartmentQueues($prescription->visit, 'PHA', $actor);
                }
            } else {
                $this->visitClosure->startDepartmentQueues($prescription->visit, 'PHA', $actor);
            }
            $this->visitClosure->evaluate($prescription->visit->refresh(), $actor);

            if ($overrideReason && ! $this->billing->isCleared($prescription)) {
                ActivityLog::query()->create([
                    'user_id' => $actor->id,
                    'event' => 'pharmacy_payment_overridden',
                    'subject_type' => $prescription::class,
                    'subject_id' => $prescription->id,
                    'new_values' => ['visit_id' => $prescription->visit_id, 'reason' => $overrideReason],
                ]);
            }

            return $dispensing->refresh();
        });
    }

    public function reverseDispensing(Dispensing $dispensing, $actor, string $reason): Dispensing
    {
        return DB::transaction(function () use ($dispensing, $actor, $reason) {
            abort_unless($actor->can('pharmacy.reverse-dispensing') && $actor->belongsToCurrentFacility(), 403);
            if (blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Sababu inahitajika.']);
            }
            $dispensing = Dispensing::query()->with('items.allocations.batch')->lockForUpdate()->findOrFail($dispensing->id);
            if ($dispensing->status === DispensingStatus::Reversed) {
                throw ValidationException::withMessages(['dispensing' => 'Dispensing tayari imereversed.']);
            }
            foreach ($dispensing->items as $item) {
                foreach ($item->allocations as $allocation) {
                    $this->movements->stockIn($allocation->batch, StockMovementType::CancellationReversal, (string) $allocation->quantity, $actor, $dispensing, $reason);
                }
                $rxItem = $item->prescriptionItem;
                $newDispensed = max(0, (float) $rxItem->dispensed_quantity - (float) $item->dispensed_quantity);
                $newRemaining = max(0, (float) $rxItem->quantity - $newDispensed);
                $itemStatus = $newRemaining <= 0 ? 'dispensed' : ($newDispensed > 0 ? 'partially_dispensed' : 'prescribed');
                $rxItem->update([
                    'dispensed_quantity' => $newDispensed,
                    'remaining_quantity' => $newRemaining,
                    'dispensing_status' => $itemStatus,
                    'status' => $itemStatus,
                ]);
                $item->update(['status' => 'reversed']);
            }
            $dispensing->update(['status' => DispensingStatus::Reversed, 'updated_by' => $actor->id]);
            $prescription = $dispensing->prescription()->with(['items', 'visit'])->lockForUpdate()->firstOrFail();
            $hasRemaining = $prescription->items()->whereNull('terminal_status')->whereColumn('dispensed_quantity', '<', 'quantity')->exists();
            $hasDispensed = $prescription->items()->where('dispensed_quantity', '>', 0)->exists();
            $prescription->update([
                'status' => $hasRemaining
                    ? ($hasDispensed ? PrescriptionStatus::PartiallyDispensed : PrescriptionStatus::Prescribed)
                    : PrescriptionStatus::Dispensed,
                'dispensed_at' => $hasRemaining ? null : $prescription->dispensed_at,
                'updated_by' => $actor->id,
            ]);
            if ($hasRemaining) {
                $this->billing->releasePrescription($prescription->refresh(), $actor);
            }
            $this->visitClosure->evaluate($prescription->visit->refresh(), $actor);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'dispensing_reversed', 'subject_type' => $dispensing::class, 'subject_id' => $dispensing->id]);

            return $dispensing->refresh();
        });
    }

    private function validatePrescription(Prescription $prescription, $actor, ?string $overrideReason): void
    {
        if ($prescription->facility_id !== currentFacility()?->id) {
            abort(404);
        }
        if (! in_array($prescription->status, [PrescriptionStatus::Prescribed, PrescriptionStatus::AwaitingPayment, PrescriptionStatus::PartiallyDispensed], true)) {
            throw ValidationException::withMessages(['prescription' => 'Prescription haiwezi kudispense kwenye status hii.']);
        }
        if (! $prescription->encounter || ! $prescription->encounter->completed_at) {
            throw ValidationException::withMessages(['prescription' => 'Consultation must be completed before medicines can be dispensed.']);
        }
        $cleared = $this->billing->isCleared($prescription);
        if (! $cleared && ! $actor->can('pharmacy.override-payment')) {
            throw ValidationException::withMessages(['payment' => 'Malipo hayajakamilika.']);
        }
        if (! $cleared && $actor->can('pharmacy.override-payment') && blank($overrideReason)) {
            throw ValidationException::withMessages(['override_reason' => 'Sababu ya payment override inahitajika.']);
        }
    }
}
