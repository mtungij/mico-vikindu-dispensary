<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','dental_encounter_id','patient_id','assessment_date','plaque_index','bleeding_index','calculus_index','oral_hygiene_status','gingival_status','periodontal_diagnosis','recorded_by','notes'])]
class PeriodontalAssessment extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['assessment_date'=>'date','plaque_index'=>'decimal:2','bleeding_index'=>'decimal:2','calculus_index'=>'decimal:2']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); }
    public function encounter(): BelongsTo { return $this->belongsTo(DentalEncounter::class, 'dental_encounter_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function measurements(): HasMany { return $this->hasMany(PeriodontalMeasurement::class); }
}
