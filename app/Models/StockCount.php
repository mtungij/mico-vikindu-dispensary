<?php

namespace App\Models;

use App\Enums\StockCountStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'stock_location_id', 'count_number', 'count_date', 'status', 'counted_by', 'verified_by', 'posted_by', 'posted_at', 'notes'])]
class StockCount extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status' => StockCountStatus::class, 'count_date' => 'date', 'posted_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function location(): BelongsTo { return $this->belongsTo(StockLocation::class, 'stock_location_id'); }
    public function items(): HasMany { return $this->hasMany(StockCountItem::class); }
}
