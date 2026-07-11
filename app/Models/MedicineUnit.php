<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'name', 'symbol', 'description', 'decimal_allowed', 'is_active', 'sort_order', 'created_by', 'updated_by'])]
class MedicineUnit extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['decimal_allowed' => 'boolean', 'is_active' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
}
