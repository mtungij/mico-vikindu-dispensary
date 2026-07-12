<?php

namespace App\Models;

use App\Enums\DentalEncounterStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','patient_id','visit_id','clinical_encounter_id','provider_user_id','dental_encounter_number','complaint','complaint_duration','dental_history','medical_history_review','medication_history','allergy_history','oral_hygiene_history','previous_dental_treatment','tobacco_use','alcohol_use','brushing_frequency','flossing_frequency','dental_anxiety_level','pregnancy_status_snapshot','allergies_snapshot','current_medications_snapshot','extraoral_examination','intraoral_examination','periodontal_summary','occlusion_summary','radiographic_findings','clinical_summary','treatment_plan_summary','status','started_at','completed_at','signed_off_by','signed_off_at','amendment_reason','created_by','updated_by'])]
class DentalEncounter extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status'=>DentalEncounterStatus::class,'started_at'=>'datetime','completed_at'=>'datetime','signed_off_at'=>'datetime']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); }
    public function isCompleted(): bool { return ($this->status?->value ?? $this->status) === 'completed'; }
    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function clinicalEncounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class); }
    public function provider(): BelongsTo { return $this->belongsTo(User::class, 'provider_user_id'); }
    public function signer(): BelongsTo { return $this->belongsTo(User::class, 'signed_off_by'); }
    public function toothRecords(): HasMany { return $this->hasMany(DentalToothRecord::class); }
    public function findings(): HasMany { return $this->hasMany(DentalToothFinding::class); }
    public function examinations(): HasMany { return $this->hasMany(DentalExamination::class); }
    public function periodontalAssessments(): HasMany { return $this->hasMany(PeriodontalAssessment::class); }
    public function diagnoses(): HasMany { return $this->hasMany(DentalDiagnosis::class); }
    public function treatmentPlans(): HasMany { return $this->hasMany(DentalTreatmentPlan::class); }
    public function procedures(): HasMany { return $this->hasMany(DentalProcedure::class); }
    public function consents(): HasMany { return $this->hasMany(DentalConsent::class); }
    public function attachments(): HasMany { return $this->hasMany(DentalAttachment::class); }
    public function labOrders(): HasMany { return $this->hasMany(DentalLabOrder::class); }
}
