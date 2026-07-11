<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','code','name','category','description','color','icon','applies_to_surface','applies_to_whole_tooth','severity_enabled','is_system','is_active','sort_order','created_by','updated_by'])]
class DentalFindingType extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['applies_to_surface'=>'boolean','applies_to_whole_tooth'=>'boolean','severity_enabled'=>'boolean','is_system'=>'boolean','is_active'=>'boolean']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where(fn($q)=>$q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id)); }
    public function findings(): HasMany { return $this->hasMany(DentalToothFinding::class, 'finding_type_id'); }
}
