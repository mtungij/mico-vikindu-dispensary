<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\PatientStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'patient_number', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'age_years', 'age_months', 'date_of_birth_is_estimated', 'marital_status', 'nationality', 'nida_number', 'passport_number', 'primary_phone', 'secondary_phone', 'email', 'physical_address', 'postal_address', 'region', 'district', 'ward', 'street_or_village', 'occupation', 'religion', 'blood_group', 'rhesus_factor', 'known_allergies', 'chronic_conditions', 'disability_status', 'preferred_language', 'patient_status', 'profile_incomplete', 'passport_photo_path', 'created_by', 'updated_by', 'registered_at'])]
class Patient extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['gender' => Gender::class, 'date_of_birth' => 'date', 'date_of_birth_is_estimated' => 'boolean', 'marital_status' => MaritalStatus::class, 'patient_status' => PatientStatus::class, 'profile_incomplete' => 'boolean', 'registered_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function contacts(): HasMany { return $this->hasMany(PatientContact::class); }
    public function payerProfiles(): HasMany { return $this->hasMany(PatientPayerProfile::class); }
    public function documents(): HasMany { return $this->hasMany(PatientDocument::class); }
    public function visits(): HasMany { return $this->hasMany(Visit::class); }
    public function invoices(): HasMany { return $this->hasMany(Invoice::class); }
    public function triageAssessments(): HasMany { return $this->hasMany(TriageAssessment::class); }
    public function clinicalEncounters(): HasMany { return $this->hasMany(ClinicalEncounter::class); }
    public function diagnoses(): HasMany { return $this->hasMany(Diagnosis::class); }
    public function clinicalAlerts(): HasMany { return $this->hasMany(ClinicalAlert::class); }
    public function referrals(): HasMany { return $this->hasMany(PatientReferral::class); }
    public function appointments(): HasMany { return $this->hasMany(Appointment::class); }
    public function primaryPayerProfile(): HasOne { return $this->hasOne(PatientPayerProfile::class)->where('is_primary', true); }
    public function latestVisit(): HasOne { return $this->hasOne(Visit::class)->latestOfMany(); }
    public function activeVisit(): HasOne { return $this->hasOne(Visit::class)->whereNotIn('visit_status', ['completed', 'cancelled', 'discharged', 'referred'])->latestOfMany(); }
    public function fullName(): string { return collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' '); }
    public function initials(): string { return str($this->first_name)->substr(0, 1)->append(str($this->last_name)->substr(0, 1))->upper()->toString(); }
    public function ageLabel(): string { return $this->date_of_birth ? $this->date_of_birth->age.' yrs' : trim(($this->age_years ?? 0).' yrs '.($this->age_months ?? 0).' mo'); }
}
