<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id', 'visit_id', 'patient_id', 'from_department_id', 'to_department_id', 'movement_type', 'status', 'reason', 'moved_by', 'moved_at', 'received_by', 'received_at', 'movement_duration_seconds', 'emergency_override', 'authorized_by', 'notes'])]
class VisitMovement extends Model
{
    use HasFactory;
    protected function casts(): array { return ['moved_at' => 'datetime', 'received_at' => 'datetime', 'emergency_override' => 'boolean']; }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function fromDepartment(): BelongsTo { return $this->belongsTo(Department::class, 'from_department_id'); }
    public function toDepartment(): BelongsTo { return $this->belongsTo(Department::class, 'to_department_id'); }
    public function mover(): BelongsTo { return $this->belongsTo(User::class, 'moved_by'); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
    public function authorizer(): BelongsTo { return $this->belongsTo(User::class, 'authorized_by'); }
}
