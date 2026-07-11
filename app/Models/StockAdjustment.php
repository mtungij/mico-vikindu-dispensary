<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'adjustment_number', 'stock_location_id', 'adjustment_type', 'reason', 'status', 'requested_by', 'approved_by', 'approved_at', 'notes'])]
class StockAdjustment extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['approved_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function location(): BelongsTo { return $this->belongsTo(StockLocation::class, 'stock_location_id'); }
    public function items(): HasMany { return $this->hasMany(StockAdjustmentItem::class); }
}
