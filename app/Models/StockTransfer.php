<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'transfer_number', 'from_location_id', 'to_location_id', 'status', 'requested_by', 'requested_at', 'approved_by', 'approved_at', 'dispatched_by', 'dispatched_at', 'received_by', 'received_at', 'notes'])]
class StockTransfer extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status' => StockTransferStatus::class, 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'dispatched_at' => 'datetime', 'received_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function fromLocation(): BelongsTo { return $this->belongsTo(StockLocation::class, 'from_location_id'); }
    public function toLocation(): BelongsTo { return $this->belongsTo(StockLocation::class, 'to_location_id'); }
    public function items(): HasMany { return $this->hasMany(StockTransferItem::class); }
}
