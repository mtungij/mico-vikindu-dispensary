<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyPlanningVisit extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['visit_date' => 'date', 'method_start_date' => 'date', 'expected_end_date' => 'date', 'counselling_done' => 'boolean', 'method_changed' => 'boolean', 'next_visit_date' => 'date']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function client(): BelongsTo { return $this->belongsTo(FamilyPlanningClient::class, 'family_planning_client_id'); }
    public function selectedMethod(): BelongsTo { return $this->belongsTo(FamilyPlanningMethod::class, 'selected_method_id'); }
}
