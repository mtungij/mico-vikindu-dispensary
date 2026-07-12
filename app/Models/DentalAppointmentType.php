<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','name','code','default_duration_minutes','description','is_system','is_active','sort_order'])]
class DentalAppointmentType extends Model
{
    use SoftDeletes;
    protected function casts(): array { return ['default_duration_minutes'=>'integer','is_system'=>'boolean','is_active'=>'boolean','sort_order'=>'integer']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id)); }
}
