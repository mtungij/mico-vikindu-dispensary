<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pharmacy_return_id', 'dispensing_item_id', 'medicine_id', 'medicine_batch_id', 'quantity_returned', 'condition_status', 'restock_allowed', 'refund_amount', 'notes'])]
class PharmacyReturnItem extends Model
{
    use HasFactory;
    protected function casts(): array { return ['quantity_returned' => 'decimal:3', 'restock_allowed' => 'boolean', 'refund_amount' => 'decimal:2']; }
    public function return(): BelongsTo { return $this->belongsTo(PharmacyReturn::class, 'pharmacy_return_id'); }
    public function dispensingItem(): BelongsTo { return $this->belongsTo(DispensingItem::class); }
    public function batch(): BelongsTo { return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id'); }
}
