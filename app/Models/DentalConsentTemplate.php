<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','name','code','consent_type','content','risks','alternatives','effective_from','effective_to','is_active','created_by','updated_by'])]
class DentalConsentTemplate extends Model
{
    use SoftDeletes;
    protected function casts(): array { return ['effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id)); }
}
