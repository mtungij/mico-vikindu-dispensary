<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'name', 'code', 'description', 'icon', 'color', 'sort_order', 'is_active', 'created_by', 'updated_by'])]
class LaboratoryTestCategory extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['is_active' => 'boolean', 'sort_order' => 'integer']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function tests(): HasMany { return $this->hasMany(LaboratoryTest::class); }
}
