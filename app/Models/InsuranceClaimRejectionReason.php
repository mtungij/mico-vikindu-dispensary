<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_provider_id','code','name','category','description','correction_action','is_system','is_active','sort_order'])]
class InsuranceClaimRejectionReason extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['is_system'=>'boolean','is_active'=>'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id)); }
}
