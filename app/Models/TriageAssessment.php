<?php

namespace App\Models;

use App\Enums\ConsciousnessLevel;
use App\Enums\PregnancyStatus;
use App\Enums\TriageLevel;
use App\Enums\TriageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'patient_id', 'visit_id', 'queue_id', 'assessed_by', 'assessed_at', 'sequence_number', 'triage_level', 'chief_complaint_summary', 'temperature', 'systolic_bp', 'diastolic_bp', 'pulse_rate', 'respiratory_rate', 'oxygen_saturation', 'weight_kg', 'height_cm', 'bmi', 'blood_glucose', 'muac_cm', 'pain_score', 'consciousness_level', 'pregnancy_status', 'gestational_age_weeks', 'danger_signs', 'allergies_confirmed', 'fall_risk', 'infection_risk', 'notes', 'status', 'amendment_reason', 'created_by', 'updated_by'])]
class TriageAssessment extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'assessed_at' => 'datetime',
            'triage_level' => TriageLevel::class,
            'status' => TriageStatus::class,
            'consciousness_level' => ConsciousnessLevel::class,
            'pregnancy_status' => PregnancyStatus::class,
            'danger_signs' => 'array',
            'allergies_confirmed' => 'boolean',
            'temperature' => 'decimal:2',
            'oxygen_saturation' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'bmi' => 'decimal:2',
            'blood_glucose' => 'decimal:2',
            'muac_cm' => 'decimal:2',
        ];
    }

    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function queue(): BelongsTo { return $this->belongsTo(PatientQueue::class, 'queue_id'); }
    public function assessor(): BelongsTo { return $this->belongsTo(User::class, 'assessed_by'); }
}
