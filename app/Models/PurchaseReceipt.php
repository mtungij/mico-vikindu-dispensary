<?php

namespace App\Models;

use App\Enums\PurchaseReceiptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'supplier_id', 'purchase_order_id', 'receipt_number', 'supplier_invoice_number', 'supplier_delivery_note', 'received_at', 'stock_location_id', 'status', 'notes', 'received_by', 'verified_by', 'verified_at', 'created_by', 'updated_by'])]
class PurchaseReceipt extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status' => PurchaseReceiptStatus::class, 'received_at' => 'datetime', 'verified_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function location(): BelongsTo { return $this->belongsTo(StockLocation::class, 'stock_location_id'); }
    public function items(): HasMany { return $this->hasMany(PurchaseReceiptItem::class); }
}
