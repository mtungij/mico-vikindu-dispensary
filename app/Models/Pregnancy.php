<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pregnancy extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected function casts(): array { return ['lmp_date' => 'date', 'lmp_is_certain' => 'boolean', 'estimated_delivery_date' => 'date', 'multiple_pregnancy' => 'boolean', 'high_risk' => 'boolean', 'registered_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function datingRecords(): HasMany { return $this->hasMany(PregnancyDatingRecord::class); }
    public function obstetricHistory(): HasMany { return $this->hasMany(ObstetricHistoryRecord::class); }
    public function ancRegistration(): HasOne { return $this->hasOne(AncRegistration::class); }
    public function ancVisits(): HasMany { return $this->hasMany(AncVisit::class); }
    public function riskFactors(): HasMany { return $this->hasMany(PregnancyRiskFactor::class); }
    public function birthPreparednessPlan(): HasOne { return $this->hasOne(BirthPreparednessPlan::class); }
    public function pmtctRecords(): HasMany { return $this->hasMany(PmtctRecord::class); }
}
