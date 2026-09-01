<?php

namespace App\Services;

use App\Enums\ServiceType;
use App\Models\ActivityLog;
use App\Models\Medicine;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MedicineCatalogService
{
    private const ACTIVE_PRESCRIPTION_STATUSES = ['draft', 'awaiting_payment', 'prescribed', 'partially_dispensed'];

    public function createMedicine(array $data, $actor): Medicine
    {
        $cashPrice = $data['cash_price'] ?? null;
        unset($data['cash_price']);

        return DB::transaction(function () use ($data, $cashPrice, $actor) {
            if (! empty($data['service_id'])) {
                $service = Service::query()->where('facility_id', currentFacility()->id)->findOrFail($data['service_id']);
                if ($service->service_type !== ServiceType::Medicine) {
                    throw ValidationException::withMessages(['service_id' => 'Service lazima iwe ya type medicine.']);
                }
            }
            $medicine = Medicine::query()->create([...$data, 'facility_id' => currentFacility()->id, 'code' => str($data['code'])->upper(), 'created_by' => $actor->id]);
            app(MedicineBillingSetupService::class)->setup($medicine, $cashPrice, $actor);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'medicine_created', 'subject_type' => $medicine::class, 'subject_id' => $medicine->id]);

            return $medicine->refresh();
        });
    }

    public function updateMedicine(Medicine $medicine, array $data, $actor): Medicine
    {
        $cashPrice = $data['cash_price'] ?? null;
        unset($data['cash_price']);

        return DB::transaction(function () use ($medicine, $data, $cashPrice, $actor): Medicine {
            abort_unless($medicine->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);
            if (! empty($data['service_id'])) {
                $service = Service::query()->where('facility_id', $medicine->facility_id)->findOrFail($data['service_id']);
                if ($service->service_type !== ServiceType::Medicine) {
                    throw ValidationException::withMessages(['service_id' => 'Service lazima iwe ya type medicine.']);
                }
            }
            $old = $medicine->only(array_keys($data));
            $medicine->update([...$data, 'code' => str($data['code'])->upper(), 'updated_by' => $actor->id]);
            app(MedicineBillingSetupService::class)->setup($medicine->refresh(), $cashPrice, $actor);
            if (($old['service_id'] ?? null) !== $medicine->service_id) {
                ActivityLog::query()->create([
                    'user_id' => $actor->id,
                    'event' => 'medicine_billing_service_manual_correction',
                    'subject_type' => $medicine::class,
                    'subject_id' => $medicine->id,
                    'old_values' => ['facility_id' => $medicine->facility_id, 'medicine_id' => $medicine->id, 'service_id' => $old['service_id'] ?? null],
                    'new_values' => ['facility_id' => $medicine->facility_id, 'medicine_id' => $medicine->id, 'service_id' => $medicine->service_id],
                ]);
            }
            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => 'medicine_updated',
                'subject_type' => $medicine::class,
                'subject_id' => $medicine->id,
                'old_values' => $old,
                'new_values' => $medicine->fresh()->only(array_keys($data)),
            ]);

            return $medicine->refresh();
        });
    }

    /** @return array{current_stock: float, has_batches: bool, has_stock_history: bool, has_prescription_history: bool, has_active_prescription: bool, has_dispensing_history: bool, has_billing_history: bool, has_service_mapping: bool, has_other_history: bool, has_history: bool, action: string} */
    public function deletionAssessment(Medicine $medicine, $actor): array
    {
        Gate::forUser($actor)->authorize('delete', $medicine);
        abort_unless($medicine->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);

        return $this->buildDeletionAssessment($medicine);
    }

    /** @return array{outcome: string, assessment: array<string, mixed>} */
    public function removeMedicine(Medicine $medicine, $actor): array
    {
        return DB::transaction(function () use ($medicine, $actor): array {
            $medicine = Medicine::query()->lockForUpdate()->findOrFail($medicine->id);
            Gate::forUser($actor)->authorize('delete', $medicine);
            abort_unless($medicine->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);

            DB::table('medicine_batches')->where('medicine_id', $medicine->id)->lockForUpdate()->get();
            $assessment = $this->buildDeletionAssessment($medicine);
            $old = $medicine->only(['is_active', 'deleted_at']);

            if ($assessment['action'] === 'delete') {
                $medicine->update(['is_active' => false, 'updated_by' => $actor->id]);
                $medicine->delete();
                $outcome = 'deleted';
                $event = 'medicine_safely_deleted';
            } else {
                $medicine->update(['is_active' => false, 'updated_by' => $actor->id]);
                $outcome = 'archived';
                $event = 'medicine_archived';
            }

            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => $event,
                'subject_type' => $medicine::class,
                'subject_id' => $medicine->id,
                'old_values' => $old,
                'new_values' => [
                    'facility_id' => $medicine->facility_id,
                    'medicine_id' => $medicine->id,
                    'is_active' => false,
                    'current_stock' => $assessment['current_stock'],
                    'has_history' => $assessment['has_history'],
                    'has_active_prescription' => $assessment['has_active_prescription'],
                    'service_id' => $medicine->service_id,
                    'outcome' => $outcome,
                ],
            ]);

            return ['outcome' => $outcome, 'assessment' => $assessment];
        });
    }

    /** @return array{current_stock: float, has_batches: bool, has_stock_history: bool, has_prescription_history: bool, has_active_prescription: bool, has_dispensing_history: bool, has_billing_history: bool, has_service_mapping: bool, has_other_history: bool, has_history: bool, action: string} */
    private function buildDeletionAssessment(Medicine $medicine): array
    {
        $medicineId = $medicine->id;
        $serviceId = $medicine->service_id;
        $hasBatches = DB::table('medicine_batches')->where('medicine_id', $medicineId)->exists();
        $currentStock = (float) DB::table('medicine_batches')->where('medicine_id', $medicineId)->sum('available_quantity');
        $hasStockHistory = $hasBatches || $this->existsInAny($medicineId, [
            ['stock_movements', 'medicine_id'],
            ['purchase_order_items', 'medicine_id'],
            ['purchase_receipt_items', 'medicine_id'],
            ['stock_transfer_items', 'medicine_id'],
            ['stock_adjustment_items', 'medicine_id'],
            ['stock_count_items', 'medicine_id'],
            ['pharmacy_return_items', 'medicine_id'],
            ['supplier_return_items', 'medicine_id'],
        ]);
        $hasPrescriptionHistory = DB::table('prescription_items')
            ->where(fn ($query) => $query->where('medicine_id', $medicineId)->orWhere('substitution_medicine_id', $medicineId))
            ->exists();
        $hasActivePrescription = DB::table('prescription_items')
            ->join('prescriptions', 'prescriptions.id', '=', 'prescription_items.prescription_id')
            ->whereNull('prescription_items.deleted_at')
            ->whereNull('prescriptions.deleted_at')
            ->where(fn ($query) => $query->where('prescription_items.medicine_id', $medicineId)->orWhere('prescription_items.substitution_medicine_id', $medicineId))
            ->whereIn('prescriptions.status', self::ACTIVE_PRESCRIPTION_STATUSES)
            ->exists();
        $hasDispensingHistory = DB::table('dispensing_items')
            ->where(fn ($query) => $query->where('medicine_id', $medicineId)->orWhere('substitution_from_medicine_id', $medicineId))
            ->exists();
        $hasBillingHistory = $serviceId !== null && DB::table('invoice_items')->where('service_id', $serviceId)->exists();
        $hasServiceMapping = $serviceId !== null;
        $hasOtherHistory = $this->existsInAny($medicineId, [
            ['medicine_packagings', 'medicine_id'],
            ['dental_anaesthetic_types', 'medicine_id'],
            ['dental_materials', 'medicine_id'],
            ['insurance_benefit_limits', 'medicine_id'],
            ['insurance_coverage_rules', 'medicine_id'],
            ['insurance_claim_items', 'medicine_id'],
            ['insurance_medicine_code_mappings', 'medicine_id'],
            ['medication_administrations', 'medicine_id'],
            ['iv_fluid_administrations', 'medicine_id'],
            ['family_planning_methods', 'medicine_id'],
            ['vaccines', 'medicine_id'],
        ]);
        $hasHistory = $hasStockHistory
            || $hasPrescriptionHistory
            || $hasDispensingHistory
            || $hasBillingHistory
            || $hasServiceMapping
            || $hasOtherHistory;

        return [
            'current_stock' => $currentStock,
            'has_batches' => $hasBatches,
            'has_stock_history' => $hasStockHistory,
            'has_prescription_history' => $hasPrescriptionHistory,
            'has_active_prescription' => $hasActivePrescription,
            'has_dispensing_history' => $hasDispensingHistory,
            'has_billing_history' => $hasBillingHistory,
            'has_service_mapping' => $hasServiceMapping,
            'has_other_history' => $hasOtherHistory,
            'has_history' => $hasHistory,
            'action' => $hasHistory || $currentStock > 0 ? 'archive' : 'delete',
        ];
    }

    /** @param array<int, array{0: string, 1: string}> $references */
    private function existsInAny(int $medicineId, array $references): bool
    {
        foreach ($references as [$table, $column]) {
            if (DB::table($table)->where($column, $medicineId)->exists()) {
                return true;
            }
        }

        return false;
    }
}
