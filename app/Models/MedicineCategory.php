<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'name', 'code', 'description', 'parent_id', 'icon', 'color', 'sort_order', 'is_active', 'created_by', 'updated_by'])]
class MedicineCategory extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function medicines(): HasMany { return $this->hasMany(Medicine::class); }
}
