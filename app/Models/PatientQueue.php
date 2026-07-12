<?php

namespace App\Models;

use App\Enums\QueueStatus;
use App\Enums\VisitPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id', 'visit_id', 'patient_id', 'department_id', 'queue_number', 'queue_date', 'queue_status', 'priority', 'position', 'checked_in_at', 'called_at', 'service_started_at', 'service_completed_at', 'skipped_at', 'cancelled_at', 'waiting_seconds', 'service_seconds', 'requeued_at', 'assigned_to_user_id', 'notes', 'created_by'])]
class PatientQueue extends Model
{
    use HasFactory;
    protected function casts(): array { return ['queue_date' => 'date', 'queue_status' => QueueStatus::class, 'priority' => VisitPriority::class, 'checked_in_at' => 'datetime', 'called_at' => 'datetime', 'service_started_at' => 'datetime', 'service_completed_at' => 'datetime', 'skipped_at' => 'datetime', 'cancelled_at' => 'datetime', 'requeued_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to_user_id'); }
}
