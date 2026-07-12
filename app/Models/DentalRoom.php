<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','department_id','name','code','location','is_active','notes'])]
class DentalRoom extends Model
{
    use SoftDeletes;
    protected function casts(): array { return ['is_active'=>'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function chairs(): HasMany { return $this->hasMany(DentalChair::class); }
}
