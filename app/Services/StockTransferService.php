<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\ActivityLog;
use App\Models\StockLocation;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(private readonly DispensingNumberService $numbers, private readonly StockMovementService $movements) {}
    public function create(array $data, array $items, $actor): StockTransfer
    {
        $from = StockLocation::query()->where('facility_id', currentFacility()->id)->findOrFail($data['from_location_id']);
        $to = StockLocation::query()->where('facility_id', currentFacility()->id)->findOrFail($data['to_location_id']);
        if ($from->id === $to->id) throw ValidationException::withMessages(['to_location_id' => 'Locations lazima ziwe tofauti.']);
        return DB::transaction(function () use ($from, $to, $items, $actor, $data) {
            $transfer = StockTransfer::query()->create(['facility_id' => currentFacility()->id, 'transfer_number' => $this->numbers->transfer(currentFacility()->id), 'from_location_id' => $from->id, 'to_location_id' => $to->id, 'status' => StockTransferStatus::Requested, 'requested_by' => $actor->id, 'requested_at' => now(), 'notes' => $data['notes'] ?? null]);
            foreach ($items as $line) $transfer->items()->create(['medicine_id' => $line['medicine_id'], 'medicine_batch_id' => $line['medicine_batch_id'], 'requested_quantity' => $line['quantity'], 'notes' => $line['notes'] ?? null]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'stock_transferred', 'subject_type' => $transfer::class, 'subject_id' => $transfer->id]);
            return $transfer->refresh();
        });
    }
    public function dispatch(StockTransfer $transfer, $actor): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $actor) {
            if ($transfer->status === StockTransferStatus::Dispatched) throw ValidationException::withMessages(['transfer' => 'Transfer tayari dispatched.']);
            foreach ($transfer->items()->with('batch')->get() as $item) {
                $this->movements->stockOut($item->batch, StockMovementType::TransferOut, (string) $item->requested_quantity, $actor, $transfer, 'Stock transfer out');
                $item->update(['dispatched_quantity' => $item->requested_quantity]);
            }
            $transfer->update(['status' => StockTransferStatus::Dispatched, 'dispatched_by' => $actor->id, 'dispatched_at' => now()]);
            return $transfer->refresh();
        });
    }
    public function receive(StockTransfer $transfer, $actor): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $actor) {
            if ($transfer->status === StockTransferStatus::Received) throw ValidationException::withMessages(['transfer' => 'Transfer tayari received.']);
            foreach ($transfer->items()->with('batch')->get() as $item) {
                $newBatch = \App\Models\MedicineBatch::query()->firstOrCreate(['facility_id' => $transfer->facility_id, 'medicine_id' => $item->medicine_id, 'stock_location_id' => $transfer->to_location_id, 'batch_number' => $item->batch->batch_number], ['supplier_id' => $item->batch->supplier_id, 'expiry_date' => $item->batch->expiry_date, 'received_quantity' => 0, 'available_quantity' => 0, 'unit_cost' => $item->batch->unit_cost, 'status' => 'active', 'received_at' => now(), 'created_by' => $actor->id]);
                $this->movements->stockIn($newBatch, StockMovementType::TransferIn, (string) $item->dispatched_quantity, $actor, $transfer, 'Stock transfer in');
                $item->update(['received_quantity' => $item->dispatched_quantity]);
            }
            $transfer->update(['status' => StockTransferStatus::Received, 'received_by' => $actor->id, 'received_at' => now()]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'transfer_received', 'subject_type' => $transfer::class, 'subject_id' => $transfer->id]);
            return $transfer->refresh();
        });
    }
}
