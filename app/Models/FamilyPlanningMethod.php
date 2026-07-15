<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyPlanningMethod extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['requires_procedure' => 'boolean', 'requires_prescription' => 'boolean', 'requires_inventory_item' => 'boolean', 'is_system' => 'boolean', 'is_active' => 'boolean']; }
    public function scopeForCurrentFacilityOrSystem(Builder $query): Builder { return $query->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function medicine(): BelongsTo { return $this->belongsTo(Medicine::class); }
}
