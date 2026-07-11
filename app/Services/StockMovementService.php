<?php

namespace App\Services;

use App\Enums\MedicineBatchStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\ActivityLog;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    public function stockIn(MedicineBatch $batch, string|StockMovementType $type, string $quantity, $actor, mixed $reference = null, ?string $reason = null, ?string $notes = null): StockMovement
    {
        $batch = MedicineBatch::query()->lockForUpdate()->findOrFail($batch->id);
        $before = (float) $batch->available_quantity;
        $after = $before + (float) $quantity;
        $batch->update(['available_quantity' => $after, 'status' => $after > 0 && $batch->status === MedicineBatchStatus::Exhausted ? MedicineBatchStatus::Active : $batch->status, 'updated_by' => $actor->id]);
        return $this->movement($batch, $type, StockMovementDirection::In, $quantity, $before, $after, $actor, $reference, $reason, $notes);
    }

    public function stockOut(MedicineBatch $batch, string|StockMovementType $type, string $quantity, $actor, mixed $reference = null, ?string $reason = null, ?string $notes = null, bool $allowNegative = false): StockMovement
    {
        $batch = MedicineBatch::query()->lockForUpdate()->findOrFail($batch->id);
        if (! $this->isDispensable($batch) && in_array(($type instanceof StockMovementType ? $type->value : $type), ['dispensing', 'transfer_out'], true)) {
            throw ValidationException::withMessages(['batch' => 'Batch hii haiwezi kutumika kwa dispensing/transfer.']);
        }
        $before = (float) $batch->available_quantity;
        if (! $allowNegative && $before < (float) $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Stock haitoshi.']);
        }
        $after = $before - (float) $quantity;
        $batch->update(['available_quantity' => $after, 'status' => $after <= 0 ? MedicineBatchStatus::Exhausted : $batch->status, 'updated_by' => $actor->id]);
        return $this->movement($batch, $type, StockMovementDirection::Out, $quantity, $before, $after, $actor, $reference, $reason, $notes);
    }

    public function openingStock(Medicine $medicine, StockLocation $location, array $data, $actor): MedicineBatch
    {
        $batch = MedicineBatch::query()->create([
            'facility_id' => $medicine->facility_id,
            'medicine_id' => $medicine->id,
            'stock_location_id' => $location->id,
            'supplier_id' => $data['supplier_id'] ?? null,
            'batch_number' => $data['batch_number'],
            'manufacturing_date' => $data['manufacturing_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'received_quantity' => 0,
            'available_quantity' => 0,
            'unit_cost' => $data['unit_cost'],
            'selling_price_snapshot' => $data['selling_price'] ?? null,
            'status' => MedicineBatchStatus::Active,
            'received_at' => now(),
            'created_by' => $actor->id,
        ]);
        $this->stockIn($batch, StockMovementType::OpeningStock, (string) $data['quantity'], $actor, $batch, $data['reason'] ?? 'Opening stock', $data['notes'] ?? null);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'opening_stock_posted', 'subject_type' => $batch::class, 'subject_id' => $batch->id]);
        return $batch->refresh();
    }

    public function reverseMovement(StockMovement $movement, $actor, string $reason): StockMovement
    {
        $batch = $movement->batch()->lockForUpdate()->firstOrFail();
        return $movement->direction === StockMovementDirection::Out
            ? $this->stockIn($batch, StockMovementType::CancellationReversal, (string) $movement->quantity, $actor, $movement, $reason)
            : $this->stockOut($batch, StockMovementType::CancellationReversal, (string) $movement->quantity, $actor, $movement, $reason);
    }

    public function getBalance(Medicine $medicine, ?StockLocation $location = null): float
    {
        return (float) $medicine->batches()->where('status', MedicineBatchStatus::Active)->when($location, fn ($q) => $q->where('stock_location_id', $location->id))->sum('available_quantity');
    }

    private function movement(MedicineBatch $batch, string|StockMovementType $type, StockMovementDirection $direction, string $quantity, float $before, float $after, $actor, mixed $reference, ?string $reason, ?string $notes): StockMovement
    {
        return StockMovement::query()->create([
            'facility_id' => $batch->facility_id,
            'medicine_id' => $batch->medicine_id,
            'medicine_batch_id' => $batch->id,
            'stock_location_id' => $batch->stock_location_id,
            'movement_type' => $type instanceof StockMovementType ? $type : StockMovementType::from($type),
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_cost' => $batch->unit_cost,
            'balance_before' => $before,
            'balance_after' => $after,
            'reference_type' => is_object($reference) ? $reference::class : null,
            'reference_id' => is_object($reference) ? $reference->id : null,
            'reason' => $reason,
            'notes' => $notes,
            'performed_by' => $actor->id,
            'occurred_at' => now(),
        ]);
    }

    private function isDispensable(MedicineBatch $batch): bool
    {
        return $batch->status === MedicineBatchStatus::Active && (! $batch->expiry_date || $batch->expiry_date->isFuture() || $batch->expiry_date->isToday());
    }
}
