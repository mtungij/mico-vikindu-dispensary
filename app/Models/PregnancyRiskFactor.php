<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PregnancyRiskFactor extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['detected_at' => 'datetime', 'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function pregnancy(): BelongsTo { return $this->belongsTo(Pregnancy::class); }
    public function type(): BelongsTo { return $this->belongsTo(PregnancyRiskFactorType::class, 'risk_factor_type_id'); }
    public function ancVisit(): BelongsTo { return $this->belongsTo(AncVisit::class); }
}
