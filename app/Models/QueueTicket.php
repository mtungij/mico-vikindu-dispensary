<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id', 'patient_queue_id', 'visit_id', 'patient_id', 'department_id', 'queue_number', 'visit_number', 'patient_name', 'qr_payload', 'printed_at', 'printed_by'])]
class QueueTicket extends Model
{
    protected function casts(): array { return ['printed_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function queue(): BelongsTo { return $this->belongsTo(PatientQueue::class, 'patient_queue_id'); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
}
