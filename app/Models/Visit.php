<?php

namespace App\Models;

use App\Enums\PayerType;
use App\Enums\VisitPriority;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'patient_id', 'visit_number', 'visit_type', 'payer_type', 'patient_payer_profile_id', 'destination_department_id', 'current_department_id', 'current_queue_id', 'current_assigned_user_id', 'consultation_service_id', 'visit_status', 'priority', 'source', 'reason_for_visit', 'referral_source', 'referral_number', 'arrived_at', 'registered_at', 'checked_in_at', 'completed_at', 'cancelled_at', 'cancellation_reason', 'created_by', 'updated_by'])]
class Visit extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['visit_type' => VisitType::class, 'payer_type' => PayerType::class, 'visit_status' => VisitStatus::class, 'priority' => VisitPriority::class, 'arrived_at' => 'datetime', 'registered_at' => 'datetime', 'checked_in_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function destinationDepartment(): BelongsTo { return $this->belongsTo(Department::class, 'destination_department_id'); }
    public function currentDepartment(): BelongsTo { return $this->belongsTo(Department::class, 'current_department_id'); }
    public function currentQueue(): BelongsTo { return $this->belongsTo(PatientQueue::class, 'current_queue_id'); }
    public function currentAssignedUser(): BelongsTo { return $this->belongsTo(User::class, 'current_assigned_user_id'); }
    public function consultationService(): BelongsTo { return $this->belongsTo(Service::class, 'consultation_service_id'); }
    public function payerProfile(): BelongsTo { return $this->belongsTo(PatientPayerProfile::class, 'patient_payer_profile_id'); }
    public function invoice(): HasOne { return $this->hasOne(Invoice::class); }
    public function queues(): HasMany { return $this->hasMany(PatientQueue::class); }
    public function movements(): HasMany { return $this->hasMany(VisitMovement::class); }
    public function triageAssessments(): HasMany { return $this->hasMany(TriageAssessment::class); }
    public function latestTriageAssessment(): HasOne { return $this->hasOne(TriageAssessment::class)->latestOfMany(); }
    public function clinicalEncounters(): HasMany { return $this->hasMany(ClinicalEncounter::class); }
    public function activeClinicalEncounter(): HasOne { return $this->hasOne(ClinicalEncounter::class)->whereNotIn('status', ['completed', 'cancelled', 'referred'])->latestOfMany(); }
}
