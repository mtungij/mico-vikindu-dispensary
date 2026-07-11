<?php

namespace App\Models;

use App\Enums\BedAssignmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id','observation_admission_id','patient_id','bed_id','room_id','assigned_by','assigned_at','released_by','released_at','assignment_status','transfer_reason','notes'])]
class BedAssignment extends Model { use HasFactory; protected function casts(): array { return ['assignment_status'=>BedAssignmentStatus::class,'assigned_at'=>'datetime','released_at'=>'datetime']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } public function patient(): BelongsTo { return $this->belongsTo(Patient::class); } public function bed(): BelongsTo { return $this->belongsTo(Bed::class); } public function room(): BelongsTo { return $this->belongsTo(ObservationRoom::class, 'room_id'); } }
