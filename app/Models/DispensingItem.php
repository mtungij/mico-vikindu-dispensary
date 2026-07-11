<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['dispensing_id', 'prescription_item_id', 'medicine_id', 'medicine_batch_id', 'prescribed_quantity', 'dispensed_quantity', 'unit_price_snapshot', 'total_amount', 'patient_amount', 'insurance_amount', 'payer_amount', 'substitution_from_medicine_id', 'substitution_reason', 'instructions_snapshot', 'status', 'created_by'])]
class DispensingItem extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['prescribed_quantity' => 'decimal:3', 'dispensed_quantity' => 'decimal:3', 'unit_price_snapshot' => 'decimal:2', 'total_amount' => 'decimal:2', 'patient_amount' => 'decimal:2', 'insurance_amount' => 'decimal:2', 'payer_amount' => 'decimal:2']; }
    public function dispensing(): BelongsTo { return $this->belongsTo(Dispensing::class); }
    public function prescriptionItem(): BelongsTo { return $this->belongsTo(PrescriptionItem::class); }
    public function medicine(): BelongsTo { return $this->belongsTo(Medicine::class); }
    public function batch(): BelongsTo { return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id'); }
    public function allocations(): HasMany { return $this->hasMany(DispensingBatchAllocation::class); }
}
