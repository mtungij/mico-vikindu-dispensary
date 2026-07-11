<?php

namespace App\Services;

use App\Enums\PharmacyReturnStatus;
use App\Enums\StockMovementType;
use App\Models\ActivityLog;
use App\Models\Dispensing;
use App\Models\PharmacyReturn;
use Illuminate\Support\Facades\DB;

class PharmacyReturnService
{
    public function __construct(private readonly DispensingNumberService $numbers, private readonly StockMovementService $movements) {}
    public function receive(Dispensing $dispensing, array $data, array $items, $actor): PharmacyReturn
    {
        return DB::transaction(function () use ($dispensing, $data, $items, $actor) {
            $return = PharmacyReturn::query()->create(['facility_id' => $dispensing->facility_id, 'dispensing_id' => $dispensing->id, 'patient_id' => $dispensing->patient_id, 'return_number' => $this->numbers->patientReturn($dispensing->facility_id), 'status' => PharmacyReturnStatus::Received, 'reason' => $data['reason'], 'returned_by_user_id' => $data['returned_by_user_id'] ?? null, 'received_by' => $actor->id, 'returned_at' => $data['returned_at'] ?? now(), 'notes' => $data['notes'] ?? null]);
            foreach ($items as $line) {
                $dispensingItem = $dispensing->items()->findOrFail($line['dispensing_item_id']);
                $returnItem = $return->items()->create(['dispensing_item_id' => $dispensingItem->id, 'medicine_id' => $dispensingItem->medicine_id, 'medicine_batch_id' => $line['medicine_batch_id'] ?? $dispensingItem->medicine_batch_id, 'quantity_returned' => $line['quantity_returned'], 'condition_status' => $line['condition_status'], 'restock_allowed' => $line['restock_allowed'] ?? false, 'refund_amount' => $line['refund_amount'] ?? null, 'notes' => $line['notes'] ?? null]);
                if ($returnItem->restock_allowed && $returnItem->medicine_batch_id) $this->movements->stockIn($returnItem->dispensingItem?->batch ?? \App\Models\MedicineBatch::findOrFail($returnItem->medicine_batch_id), StockMovementType::ReturnFromPatient, (string) $returnItem->quantity_returned, $actor, $return, 'Patient return');
            }
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'patient_return_received', 'subject_type' => $return::class, 'subject_id' => $return->id]);
            return $return->refresh();
        });
    }
}
