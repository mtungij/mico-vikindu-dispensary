<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dispensing_item_id', 'medicine_batch_id', 'quantity', 'unit_cost_snapshot', 'expiry_date_snapshot'])]
class DispensingBatchAllocation extends Model
{
    use HasFactory;
    protected function casts(): array { return ['quantity' => 'decimal:3', 'unit_cost_snapshot' => 'decimal:4', 'expiry_date_snapshot' => 'date']; }
    public function item(): BelongsTo { return $this->belongsTo(DispensingItem::class, 'dispensing_item_id'); }
    public function batch(): BelongsTo { return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id'); }
}
