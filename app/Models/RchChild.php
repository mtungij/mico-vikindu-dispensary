<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RchChild extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['birth_date' => 'date', 'registered_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class, 'child_patient_id'); }
    public function mother(): BelongsTo { return $this->belongsTo(Patient::class, 'mother_patient_id'); }
    public function growthMeasurements(): HasMany { return $this->hasMany(ChildGrowthMeasurement::class); }
    public function immunizations(): HasMany { return $this->hasMany(ImmunizationAdministration::class); }
}
