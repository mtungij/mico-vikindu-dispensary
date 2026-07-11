<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'department_id', 'name', 'code', 'location_type', 'description', 'is_dispensing_location', 'is_receiving_location', 'allows_transfers', 'is_active', 'created_by', 'updated_by'])]
class StockLocation extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['is_dispensing_location' => 'boolean', 'is_receiving_location' => 'boolean', 'allows_transfers' => 'boolean', 'is_active' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function batches(): HasMany { return $this->hasMany(MedicineBatch::class); }
}
