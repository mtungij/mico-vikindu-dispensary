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

#[Fillable(['facility_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'appointment_number', 'department_id', 'assigned_to_user_id', 'staff_id', 'service_id', 'appointment_type', 'appointment_date', 'appointment_time', 'estimated_duration', 'priority', 'scheduled_start', 'scheduled_end', 'status', 'reason', 'notes', 'reminder_status', 'reminder_sms_sent', 'reminder_whatsapp_sent', 'reminder_date', 'booked_by', 'checked_in_by', 'checked_in_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason', 'rescheduled_from', 'created_by', 'updated_by'])]
class Appointment extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['appointment_type' => AppointmentType::class, 'status' => AppointmentStatus::class, 'appointment_date' => 'date', 'scheduled_start' => 'datetime', 'scheduled_end' => 'datetime', 'checked_in_at' => 'datetime', 'cancelled_at' => 'datetime', 'reminder_sms_sent' => 'boolean', 'reminder_whatsapp_sent' => 'boolean', 'reminder_date' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function staff(): BelongsTo { return $this->belongsTo(User::class, 'staff_id'); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to_user_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id'); }
}
