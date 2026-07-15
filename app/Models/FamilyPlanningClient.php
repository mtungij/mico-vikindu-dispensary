<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyPlanningClient extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['registration_date' => 'date']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function currentMethod(): BelongsTo { return $this->belongsTo(FamilyPlanningMethod::class, 'current_method_id'); }
    public function visits(): HasMany { return $this->hasMany(FamilyPlanningVisit::class); }
    public function methodEpisodes(): HasMany { return $this->hasMany(FamilyPlanningMethodEpisode::class); }
}
