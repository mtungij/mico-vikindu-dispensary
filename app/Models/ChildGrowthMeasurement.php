<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChildGrowthMeasurement extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['measured_at' => 'datetime', 'edema_present' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function child(): BelongsTo { return $this->belongsTo(RchChild::class, 'rch_child_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class, 'child_patient_id'); }
    public function nutritionAssessment(): HasOne { return $this->hasOne(ChildNutritionAssessment::class); }
}
