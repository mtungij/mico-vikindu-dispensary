<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_transfer_id', 'medicine_id', 'medicine_batch_id', 'requested_quantity', 'dispatched_quantity', 'received_quantity', 'rejected_quantity', 'notes'])]
class StockTransferItem extends Model
{
    use HasFactory;
    protected function casts(): array { return ['requested_quantity' => 'decimal:3', 'dispatched_quantity' => 'decimal:3', 'received_quantity' => 'decimal:3', 'rejected_quantity' => 'decimal:3']; }
    public function transfer(): BelongsTo { return $this->belongsTo(StockTransfer::class, 'stock_transfer_id'); }
    public function medicine(): BelongsTo { return $this->belongsTo(Medicine::class); }
    public function batch(): BelongsTo { return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id'); }
}
