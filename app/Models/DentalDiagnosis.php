<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','dental_encounter_id','patient_id','visit_id','tooth_number','surface','diagnosis_type','diagnosis_name','icd10_code','certainty','is_primary','status','diagnosed_by','diagnosed_at','notes','created_by','updated_by'])]
class DentalDiagnosis extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['is_primary'=>'boolean','diagnosed_at'=>'datetime']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); }
    public function encounter(): BelongsTo { return $this->belongsTo(DentalEncounter::class, 'dental_encounter_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
}
