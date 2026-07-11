<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'laboratory_test_id', 'laboratory_test_parameter_id', 'gender', 'minimum_age_days', 'maximum_age_days', 'pregnancy_status', 'lower_limit', 'upper_limit', 'textual_range', 'unit', 'interpretation', 'is_active', 'priority', 'created_by', 'updated_by'])]
class LaboratoryReferenceRange extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['is_active' => 'boolean', 'lower_limit' => 'decimal:4', 'upper_limit' => 'decimal:4']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function test(): BelongsTo { return $this->belongsTo(LaboratoryTest::class, 'laboratory_test_id'); }
    public function parameter(): BelongsTo { return $this->belongsTo(LaboratoryTestParameter::class, 'laboratory_test_parameter_id'); }
}
