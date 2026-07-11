<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\ActivityLog;
use App\Models\MedicineBatch;
use App\Models\StockAdjustment;
use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(private readonly DispensingNumberService $numbers, private readonly StockMovementService $movements) {}
    public function post(array $data, array $items, $actor): StockAdjustment
    {
        return DB::transaction(function () use ($data, $items, $actor) {
            $location = StockLocation::query()->where('facility_id', currentFacility()->id)->findOrFail($data['stock_location_id']);
            $adjustment = StockAdjustment::query()->create(['facility_id' => currentFacility()->id, 'adjustment_number' => $this->numbers->adjustment(currentFacility()->id), 'stock_location_id' => $location->id, 'adjustment_type' => $data['adjustment_type'], 'reason' => $data['reason'], 'status' => 'posted', 'requested_by' => $actor->id, 'approved_by' => $actor->id, 'approved_at' => now(), 'notes' => $data['notes'] ?? null]);
            foreach ($items as $line) {
                $batch = MedicineBatch::query()->where('facility_id', currentFacility()->id)->findOrFail($line['medicine_batch_id']);
                $system = (float) $batch->available_quantity;
                $adjusted = (float) $line['adjusted_quantity'];
                $difference = $adjusted - $system;
                $adjustment->items()->create(['medicine_id' => $batch->medicine_id, 'medicine_batch_id' => $batch->id, 'system_quantity' => $system, 'adjusted_quantity' => $adjusted, 'difference_quantity' => $difference, 'unit_cost' => $batch->unit_cost, 'reason' => $line['reason'] ?? null]);
                if ($difference > 0) $this->movements->stockIn($batch, StockMovementType::AdjustmentIn, (string) $difference, $actor, $adjustment, $data['reason']);
                if ($difference < 0) $this->movements->stockOut($batch, StockMovementType::AdjustmentOut, (string) abs($difference), $actor, $adjustment, $data['reason']);
            }
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'stock_adjusted', 'subject_type' => $adjustment::class, 'subject_id' => $adjustment->id]);
            return $adjustment->refresh();
        });
    }
}
