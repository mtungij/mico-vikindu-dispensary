<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RchEncounter extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected function casts(): array { return ['follow_up_required' => 'boolean', 'follow_up_date' => 'date', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'signed_off_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function clinicalEncounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class); }
    public function provider(): BelongsTo { return $this->belongsTo(User::class, 'provider_user_id'); }
}
