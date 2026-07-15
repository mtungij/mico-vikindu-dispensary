<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImmunizationSchedule extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['is_default' => 'boolean', 'is_active' => 'boolean', 'effective_from' => 'date', 'effective_to' => 'date']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function items(): HasMany { return $this->hasMany(ImmunizationScheduleItem::class); }
}
