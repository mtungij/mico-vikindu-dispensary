<?php

namespace App\Services;

use App\Enums\MedicineBatchStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseReceiptStatus;
use App\Enums\StockMovementType;
use App\Models\ActivityLog;
use App\Models\MedicineBatch;
use App\Models\PurchaseReceipt;
use App\Models\StockLocation;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockReceivingService
{
    public function __construct(private readonly DispensingNumberService $numbers, private readonly StockMovementService $movements) {}

    public function receive(array $data, array $items, $actor): PurchaseReceipt
    {
        return DB::transaction(function () use ($data, $items, $actor) {
            $supplier = Supplier::query()->where('facility_id', currentFacility()->id)->findOrFail($data['supplier_id']);
            $location = StockLocation::query()->where('facility_id', currentFacility()->id)->where('is_receiving_location', true)->findOrFail($data['stock_location_id']);
            $receipt = PurchaseReceipt::query()->create([
                'facility_id' => currentFacility()->id,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'receipt_number' => $this->numbers->receipt(currentFacility()->id),
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'supplier_delivery_note' => $data['supplier_delivery_note'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'stock_location_id' => $location->id,
                'status' => PurchaseReceiptStatus::Received,
                'notes' => $data['notes'] ?? null,
                'received_by' => $actor->id,
                'created_by' => $actor->id,
            ]);

            foreach ($items as $line) {
                $medicine = \App\Models\Medicine::query()->where('facility_id', currentFacility()->id)->findOrFail($line['medicine_id']);
                if ($medicine->track_batch && blank($line['batch_number'] ?? null)) throw ValidationException::withMessages(['batch_number' => 'Batch number inahitajika.']);
                if ($medicine->track_expiry && blank($line['expiry_date'] ?? null)) throw ValidationException::withMessages(['expiry_date' => 'Expiry date inahitajika.']);
                if ($medicine->track_expiry && ! empty($line['expiry_date']) && \Illuminate\Support\Carbon::parse($line['expiry_date'])->isPast() && ! $actor->can('pharmacy.manage-expiry')) {
                    throw ValidationException::withMessages(['expiry_date' => 'Expired stock haiwezi kupokelewa bila ruhusa.']);
                }
                $qty = (float) ($line['quantity_received'] ?? 0) + (float) ($line['bonus_quantity'] ?? 0);
                $receiptItem = $receipt->items()->create([
                    'purchase_order_item_id' => $line['purchase_order_item_id'] ?? null,
                    'medicine_id' => $medicine->id,
                    'packaging_id' => $line['packaging_id'] ?? null,
                    'batch_number' => $line['batch_number'] ?? 'NO-BATCH',
                    'manufacturing_date' => $line['manufacturing_date'] ?? null,
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'quantity_received' => $line['quantity_received'] ?? 0,
                    'bonus_quantity' => $line['bonus_quantity'] ?? 0,
                    'rejected_quantity' => $line['rejected_quantity'] ?? 0,
                    'unit_cost' => $line['unit_cost'],
                    'selling_price' => $line['selling_price'] ?? null,
                    'total_cost' => round(((float) ($line['quantity_received'] ?? 0)) * (float) $line['unit_cost'], 2),
                    'rejection_reason' => $line['rejection_reason'] ?? null,
                    'notes' => $line['notes'] ?? null,
                ]);
                if ($qty <= 0) continue;
                $batch = MedicineBatch::query()->firstOrCreate([
                    'facility_id' => $medicine->facility_id,
                    'medicine_id' => $medicine->id,
                    'stock_location_id' => $location->id,
                    'batch_number' => $receiptItem->batch_number,
                ], [
                    'supplier_id' => $supplier->id,
                    'purchase_receipt_item_id' => $receiptItem->id,
                    'manufacturing_date' => $receiptItem->manufacturing_date,
                    'expiry_date' => $receiptItem->expiry_date,
                    'received_quantity' => 0,
                    'available_quantity' => 0,
                    'unit_cost' => $receiptItem->unit_cost,
                    'selling_price_snapshot' => $receiptItem->selling_price,
                    'status' => MedicineBatchStatus::Active,
                    'received_at' => $receipt->received_at,
                    'created_by' => $actor->id,
                ]);
                $batch->increment('received_quantity', $qty);
                $this->movements->stockIn($batch, StockMovementType::PurchaseReceipt, (string) $qty, $actor, $receiptItem, 'Stock received');
                ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'batch_created', 'subject_type' => $batch::class, 'subject_id' => $batch->id]);
            }

            if ($receipt->purchaseOrder) {
                $receipt->purchaseOrder->update(['status' => PurchaseOrderStatus::PartiallyReceived]);
            }

            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'stock_received', 'subject_type' => $receipt::class, 'subject_id' => $receipt->id]);
            return $receipt->refresh();
        });
    }

    public function verify(PurchaseReceipt $receipt, $actor): PurchaseReceipt
    {
        if ($receipt->status !== PurchaseReceiptStatus::Received) throw ValidationException::withMessages(['receipt' => 'Receipt haiwezi ku-verify.']);
        $receipt->update(['status' => PurchaseReceiptStatus::Verified, 'verified_by' => $actor->id, 'verified_at' => now(), 'updated_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'receipt_verified', 'subject_type' => $receipt::class, 'subject_id' => $receipt->id]);
        return $receipt->refresh();
    }
}
