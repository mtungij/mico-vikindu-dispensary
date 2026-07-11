<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['facility_id', 'visit_id', 'patient_id', 'from_department_id', 'to_department_id', 'movement_type', 'status', 'reason', 'moved_by', 'moved_at', 'received_by', 'received_at', 'notes'])]
class VisitMovement extends Model
{
    use HasFactory;
    protected function casts(): array { return ['moved_at' => 'datetime', 'received_at' => 'datetime']; }
}
