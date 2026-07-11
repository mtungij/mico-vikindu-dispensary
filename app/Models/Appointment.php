<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'department_id', 'assigned_to_user_id', 'appointment_type', 'scheduled_start', 'scheduled_end', 'status', 'reason', 'notes', 'reminder_status', 'created_by', 'updated_by'])]
class Appointment extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['appointment_type' => AppointmentType::class, 'status' => AppointmentStatus::class, 'scheduled_start' => 'datetime', 'scheduled_end' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id'); }
}
