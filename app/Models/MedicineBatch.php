<?php

namespace App\Models;

use App\Enums\MedicineBatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'medicine_id', 'stock_location_id', 'supplier_id', 'purchase_receipt_item_id', 'batch_number', 'manufacturing_date', 'expiry_date', 'received_quantity', 'available_quantity', 'reserved_quantity', 'damaged_quantity', 'unit_cost', 'selling_price_snapshot', 'status', 'received_at', 'created_by', 'updated_by'])]
class MedicineBatch extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status' => MedicineBatchStatus::class, 'manufacturing_date' => 'date', 'expiry_date' => 'date', 'received_at' => 'datetime', 'received_quantity' => 'decimal:3', 'available_quantity' => 'decimal:3', 'reserved_quantity' => 'decimal:3', 'damaged_quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'selling_price_snapshot' => 'decimal:2']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function scopeDispensable(Builder $query): Builder { return $query->where('status', MedicineBatchStatus::Active)->where('available_quantity', '>', 0)->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today())); }
    public function medicine(): BelongsTo { return $this->belongsTo(Medicine::class); }
    public function location(): BelongsTo { return $this->belongsTo(StockLocation::class, 'stock_location_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function movements(): HasMany { return $this->hasMany(StockMovement::class); }
}
