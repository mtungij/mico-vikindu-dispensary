<?php

namespace App\Models;

use App\Enums\DispensingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'prescription_id', 'patient_id', 'visit_id', 'dispensing_number', 'stock_location_id', 'status', 'payment_status', 'dispensed_by', 'verified_by', 'dispensed_at', 'notes', 'created_by', 'updated_by'])]
class Dispensing extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status' => DispensingStatus::class, 'dispensed_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function prescription(): BelongsTo { return $this->belongsTo(Prescription::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function location(): BelongsTo { return $this->belongsTo(StockLocation::class, 'stock_location_id'); }
    public function dispenser(): BelongsTo { return $this->belongsTo(User::class, 'dispensed_by'); }
    public function items(): HasMany { return $this->hasMany(DispensingItem::class); }
}
