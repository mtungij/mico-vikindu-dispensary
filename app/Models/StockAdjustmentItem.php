<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_adjustment_id', 'medicine_id', 'medicine_batch_id', 'system_quantity', 'adjusted_quantity', 'difference_quantity', 'unit_cost', 'reason'])]
class StockAdjustmentItem extends Model
{
    use HasFactory;
    protected function casts(): array { return ['system_quantity' => 'decimal:3', 'adjusted_quantity' => 'decimal:3', 'difference_quantity' => 'decimal:3', 'unit_cost' => 'decimal:4']; }
    public function adjustment(): BelongsTo { return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id'); }
    public function batch(): BelongsTo { return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id'); }
}
