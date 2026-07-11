<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['purchase_receipt_id', 'purchase_order_item_id', 'medicine_id', 'packaging_id', 'batch_number', 'manufacturing_date', 'expiry_date', 'quantity_received', 'bonus_quantity', 'rejected_quantity', 'unit_cost', 'selling_price', 'total_cost', 'rejection_reason', 'notes'])]
class PurchaseReceiptItem extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['manufacturing_date' => 'date', 'expiry_date' => 'date', 'quantity_received' => 'decimal:3', 'bonus_quantity' => 'decimal:3', 'rejected_quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'selling_price' => 'decimal:2', 'total_cost' => 'decimal:2']; }
    public function receipt(): BelongsTo { return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id'); }
    public function medicine(): BelongsTo { return $this->belongsTo(Medicine::class); }
}
