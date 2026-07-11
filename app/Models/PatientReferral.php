<?php

namespace App\Models;

use App\Enums\ReferralStatus;
use App\Enums\ReferralType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'referral_number', 'referral_type', 'destination_facility_name', 'destination_department', 'destination_contact', 'reason', 'provisional_diagnosis', 'clinical_summary', 'treatment_given', 'investigations_done', 'current_medications', 'vital_signs_snapshot', 'urgency', 'transport_method', 'accompanying_person', 'referred_by', 'referred_at', 'status', 'feedback_received_at', 'feedback_notes', 'created_by', 'updated_by'])]
class PatientReferral extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['referral_type' => ReferralType::class, 'status' => ReferralStatus::class, 'vital_signs_snapshot' => 'array', 'referred_at' => 'datetime', 'feedback_received_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id'); }
}
