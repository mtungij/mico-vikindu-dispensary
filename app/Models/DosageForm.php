<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'name', 'code', 'description', 'is_liquid', 'is_injectable', 'is_active', 'sort_order', 'created_by', 'updated_by'])]
class DosageForm extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['is_liquid' => 'boolean', 'is_injectable' => 'boolean', 'is_active' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
}
