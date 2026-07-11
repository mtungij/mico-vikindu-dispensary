<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_count_id', 'medicine_id', 'medicine_batch_id', 'system_quantity', 'counted_quantity', 'variance_quantity', 'unit_cost', 'variance_value', 'notes'])]
class StockCountItem extends Model
{
    use HasFactory;
    protected function casts(): array { return ['system_quantity' => 'decimal:3', 'counted_quantity' => 'decimal:3', 'variance_quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'variance_value' => 'decimal:2']; }
    public function count(): BelongsTo { return $this->belongsTo(StockCount::class, 'stock_count_id'); }
}
