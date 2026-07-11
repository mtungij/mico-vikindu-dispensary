<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(private readonly DispensingNumberService $numbers) {}
    public function create(array $data, array $items, $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items, $actor) {
            $supplier = Supplier::query()->where('facility_id', currentFacility()->id)->findOrFail($data['supplier_id']);
            $order = PurchaseOrder::query()->create(['facility_id' => currentFacility()->id, 'supplier_id' => $supplier->id, 'purchase_order_number' => $this->numbers->purchaseOrder(currentFacility()->id), 'order_date' => $data['order_date'] ?? today(), 'expected_delivery_date' => $data['expected_delivery_date'] ?? null, 'status' => PurchaseOrderStatus::Draft, 'notes' => $data['notes'] ?? null, 'created_by' => $actor->id]);
            $total = 0;
            foreach ($items as $line) {
                $lineTotal = round((float) $line['ordered_quantity'] * (float) $line['unit_cost'], 2);
                $order->items()->create(['medicine_id' => $line['medicine_id'], 'packaging_id' => $line['packaging_id'] ?? null, 'ordered_quantity' => $line['ordered_quantity'], 'unit_cost' => $line['unit_cost'], 'total_amount' => $lineTotal, 'notes' => $line['notes'] ?? null]);
                $total += $lineTotal;
            }
            $order->update(['subtotal' => $total, 'total_amount' => $total]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'purchase_order_created', 'subject_type' => $order::class, 'subject_id' => $order->id]);
            return $order->refresh();
        });
    }
}
