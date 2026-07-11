<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'name', 'code', 'description', 'therapeutic_class', 'pharmacological_class', 'controlled_drug', 'prescription_required', 'pregnancy_warning', 'common_indications', 'common_contraindications', 'common_side_effects', 'is_active', 'created_by', 'updated_by'])]
class GenericMedicine extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['controlled_drug' => 'boolean', 'prescription_required' => 'boolean', 'is_active' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function medicines(): HasMany { return $this->hasMany(Medicine::class); }
}
