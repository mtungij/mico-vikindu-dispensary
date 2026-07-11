<?php

namespace App\Models;

use App\Enums\DiagnosisCertainty;
use App\Enums\DiagnosisStatus;
use App\Enums\DiagnosisType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'diagnosis_type', 'icd10_code', 'diagnosis_name', 'description', 'certainty', 'is_primary', 'diagnosed_by', 'diagnosed_at', 'resolved_at', 'status', 'error_reason', 'created_by', 'updated_by'])]
class Diagnosis extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['diagnosis_type' => DiagnosisType::class, 'certainty' => DiagnosisCertainty::class, 'status' => DiagnosisStatus::class, 'is_primary' => 'boolean', 'diagnosed_at' => 'datetime', 'resolved_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
}
