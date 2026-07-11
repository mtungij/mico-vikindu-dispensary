<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\ActivityLog;
use App\Models\MedicineBatch;
use App\Models\StockCount;
use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;

class StockCountService
{
    public function __construct(private readonly DispensingNumberService $numbers, private readonly StockMovementService $movements) {}
    public function start(StockLocation $location, $actor): StockCount
    {
        return DB::transaction(function () use ($location, $actor) {
            $count = StockCount::query()->create(['facility_id' => $location->facility_id, 'stock_location_id' => $location->id, 'count_number' => $this->numbers->count($location->facility_id), 'count_date' => today(), 'status' => 'counting', 'counted_by' => $actor->id]);
            MedicineBatch::query()->where('facility_id', $location->facility_id)->where('stock_location_id', $location->id)->get()->each(fn ($batch) => $count->items()->create(['medicine_id' => $batch->medicine_id, 'medicine_batch_id' => $batch->id, 'system_quantity' => $batch->available_quantity, 'unit_cost' => $batch->unit_cost]));
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'stock_count_started', 'subject_type' => $count::class, 'subject_id' => $count->id]);
            return $count->refresh();
        });
    }
    public function post(StockCount $count, array $quantities, $actor): StockCount
    {
        return DB::transaction(function () use ($count, $quantities, $actor) {
            foreach ($count->items()->with('medicine')->get() as $item) {
                $counted = (float) ($quantities[$item->id] ?? $item->system_quantity);
                $variance = $counted - (float) $item->system_quantity;
                $item->update(['counted_quantity' => $counted, 'variance_quantity' => $variance, 'variance_value' => $variance * (float) $item->unit_cost]);
                if ($item->medicine_batch_id && $variance !== 0.0) {
                    $batch = MedicineBatch::query()->findOrFail($item->medicine_batch_id);
                    $variance > 0 ? $this->movements->stockIn($batch, StockMovementType::StockCountGain, (string) $variance, $actor, $count, 'Stock count') : $this->movements->stockOut($batch, StockMovementType::StockCountLoss, (string) abs($variance), $actor, $count, 'Stock count');
                }
            }
            $count->update(['status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now()]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'stock_count_posted', 'subject_type' => $count::class, 'subject_id' => $count->id]);
            return $count->refresh();
        });
    }
}
