<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyPlanningMethodEpisode extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['started_at' => 'date', 'expected_end_at' => 'date', 'ended_at' => 'date']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function client(): BelongsTo { return $this->belongsTo(FamilyPlanningClient::class, 'family_planning_client_id'); }
    public function method(): BelongsTo { return $this->belongsTo(FamilyPlanningMethod::class); }
}
