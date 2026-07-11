<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'name', 'code', 'description', 'container_type', 'collection_instructions', 'minimum_volume', 'volume_unit', 'storage_temperature', 'transport_instructions', 'rejection_criteria', 'is_active', 'sort_order', 'created_by', 'updated_by'])]
class SpecimenType extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['is_active' => 'boolean', 'minimum_volume' => 'decimal:2']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function tests(): HasMany { return $this->hasMany(LaboratoryTest::class); }
}
