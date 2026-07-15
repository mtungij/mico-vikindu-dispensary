<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientRelationship extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['is_primary' => 'boolean', 'start_date' => 'date', 'end_date' => 'date']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function relatedPatient(): BelongsTo { return $this->belongsTo(Patient::class, 'related_patient_id'); }
}
