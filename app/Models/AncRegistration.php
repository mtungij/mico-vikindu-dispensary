<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AncRegistration extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['registration_date' => 'date']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function pregnancy(): BelongsTo { return $this->belongsTo(Pregnancy::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
}
