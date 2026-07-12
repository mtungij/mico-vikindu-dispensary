<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','name','code','category','description','requires_tooth','requires_surface','requires_consent','requires_payment','updates_odontogram','can_require_observation','is_system','is_active','sort_order'])]
class DentalProcedureType extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['requires_tooth'=>'boolean','requires_surface'=>'boolean','requires_consent'=>'boolean','requires_payment'=>'boolean','updates_odontogram'=>'boolean','can_require_observation'=>'boolean','is_system'=>'boolean','is_active'=>'boolean','sort_order'=>'integer'];
    }

    public function scopeForCurrentFacility(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id));
    }
}
