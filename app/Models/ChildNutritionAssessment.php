<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChildNutritionAssessment extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['assessment_date' => 'date', 'referral_required' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function child(): BelongsTo { return $this->belongsTo(RchChild::class, 'rch_child_id'); }
    public function measurement(): BelongsTo { return $this->belongsTo(ChildGrowthMeasurement::class, 'child_growth_measurement_id'); }
}
