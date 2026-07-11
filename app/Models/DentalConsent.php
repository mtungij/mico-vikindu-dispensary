<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','patient_id','visit_id','dental_encounter_id','dental_procedure_id','consent_type','consent_text_snapshot','risks_explained','alternatives_explained','patient_or_guardian_name','relationship_to_patient','consent_given','signed_at','patient_signature_path','witness_user_id','clinician_user_id'])]
class DentalConsent extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['consent_given'=>'boolean','signed_at'=>'datetime']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); }
    public function encounter(): BelongsTo { return $this->belongsTo(DentalEncounter::class, 'dental_encounter_id'); }
    public function procedure(): BelongsTo { return $this->belongsTo(DentalProcedure::class, 'dental_procedure_id'); }
}
