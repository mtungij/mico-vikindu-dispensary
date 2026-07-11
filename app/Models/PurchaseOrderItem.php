<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['purchase_order_id', 'medicine_id', 'packaging_id', 'ordered_quantity', 'received_quantity', 'unit_cost', 'discount_amount', 'tax_amount', 'total_amount', 'notes'])]
class PurchaseOrderItem extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['ordered_quantity' => 'decimal:3', 'received_quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'total_amount' => 'decimal:2']; }
    public function order(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function medicine(): BelongsTo { return $this->belongsTo(Medicine::class); }
}
