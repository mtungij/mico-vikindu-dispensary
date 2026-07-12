<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','name','code','generic_name','concentration','medicine_id','route','maximum_dose_note','warnings','is_active'])]
class DentalAnaestheticType extends Model
{
    use SoftDeletes;
    protected function casts(): array { return ['is_active'=>'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id)); }
    public function medicine(): BelongsTo { return $this->belongsTo(Medicine::class); }
}
