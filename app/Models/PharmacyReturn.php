<?php

namespace App\Models;

use App\Enums\PharmacyReturnStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'dispensing_id', 'patient_id', 'return_number', 'status', 'reason', 'returned_by_user_id', 'received_by', 'returned_at', 'notes'])]
class PharmacyReturn extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status' => PharmacyReturnStatus::class, 'returned_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function dispensing(): BelongsTo { return $this->belongsTo(Dispensing::class); }
    public function items(): HasMany { return $this->hasMany(PharmacyReturnItem::class); }
}
