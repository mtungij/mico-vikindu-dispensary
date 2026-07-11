<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'medicine_id', 'name', 'purchase_unit_id', 'dispensing_unit_id', 'conversion_factor', 'barcode', 'is_default', 'is_active'])]
class MedicinePackaging extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['conversion_factor' => 'decimal:3', 'is_default' => 'boolean', 'is_active' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function medicine(): BelongsTo { return $this->belongsTo(Medicine::class); }
}
