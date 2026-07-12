<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id', 'patient_queue_id', 'department_id', 'queue_number', 'call_count', 'called_at', 'called_by'])]
class QueueCall extends Model
{
    protected function casts(): array { return ['called_at' => 'datetime', 'call_count' => 'integer']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function queue(): BelongsTo { return $this->belongsTo(PatientQueue::class, 'patient_queue_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
}
