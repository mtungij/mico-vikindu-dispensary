<?php

namespace App\Models;

use App\Enums\ClinicalEncounterStatus;
use App\Enums\ClinicalEncounterType;
use App\Enums\ClinicalOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'patient_id', 'visit_id', 'department_id', 'encounter_type', 'encounter_number', 'provider_user_id', 'started_at', 'completed_at', 'status', 'chief_complaint', 'history_of_presenting_illness', 'past_medical_history', 'surgical_history', 'medication_history', 'allergy_history', 'family_history', 'social_history', 'obstetric_history', 'gynecological_history', 'review_of_systems', 'physical_examination', 'clinical_summary', 'assessment_notes', 'treatment_plan', 'discharge_instructions', 'follow_up_required', 'follow_up_date', 'outcome', 'signed_off_by', 'signed_off_at', 'amendment_reason', 'created_by', 'updated_by'])]
class ClinicalEncounter extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'encounter_type' => ClinicalEncounterType::class,
            'status' => ClinicalEncounterStatus::class,
            'outcome' => ClinicalOutcome::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'signed_off_at' => 'datetime',
            'follow_up_date' => 'date',
            'follow_up_required' => 'boolean',
        ];
    }

    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function provider(): BelongsTo { return $this->belongsTo(User::class, 'provider_user_id'); }
    public function complaints(): HasMany { return $this->hasMany(ClinicalComplaint::class); }
    public function examinations(): HasMany { return $this->hasMany(PhysicalExamination::class); }
    public function diagnoses(): HasMany { return $this->hasMany(Diagnosis::class); }
    public function laboratoryOrders(): HasMany { return $this->hasMany(LaboratoryOrder::class); }
    public function prescriptions(): HasMany { return $this->hasMany(Prescription::class); }
    public function procedureOrders(): HasMany { return $this->hasMany(ClinicalProcedureOrder::class); }
    public function referrals(): HasMany { return $this->hasMany(PatientReferral::class); }
    public function appointments(): HasMany { return $this->hasMany(Appointment::class); }
    public function amendments(): HasMany { return $this->hasMany(ClinicalNoteAmendment::class); }
}
