<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PregnancyRiskFactorType extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['referral_recommended' => 'boolean', 'is_system' => 'boolean', 'is_active' => 'boolean']; }
    public function scopeForCurrentFacilityOrSystem(Builder $query): Builder { return $query->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id); }
}
