<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'supplier_id', 'purchase_order_number', 'order_date', 'expected_delivery_date', 'status', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'notes', 'approved_by', 'approved_at', 'created_by', 'updated_by'])]
class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status' => PurchaseOrderStatus::class, 'order_date' => 'date', 'expected_delivery_date' => 'date', 'approved_at' => 'datetime', 'subtotal' => 'decimal:2', 'total_amount' => 'decimal:2']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
}
