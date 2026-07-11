<?php

namespace App\Models;

use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id', 'medicine_id', 'medicine_batch_id', 'stock_location_id', 'movement_type', 'direction', 'quantity', 'unit_cost', 'balance_before', 'balance_after', 'reference_type', 'reference_id', 'reason', 'notes', 'performed_by', 'occurred_at', 'created_at'])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;
    protected function casts(): array { return ['movement_type' => StockMovementType::class, 'direction' => StockMovementDirection::class, 'quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'balance_before' => 'decimal:3', 'balance_after' => 'decimal:3', 'occurred_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function medicine(): BelongsTo { return $this->belongsTo(Medicine::class); }
    public function batch(): BelongsTo { return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id'); }
    public function location(): BelongsTo { return $this->belongsTo(StockLocation::class, 'stock_location_id'); }
    public function performer(): BelongsTo { return $this->belongsTo(User::class, 'performed_by'); }
}
