<?php

namespace App\Models;

use App\Enums\BedReservationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id','patient_id','visit_id','admission_id','bed_id','reserved_by','reserved_at','expires_at','status','notes'])]
class BedReservation extends Model { use HasFactory; protected function casts(): array { return ['status'=>BedReservationStatus::class,'reserved_at'=>'datetime','expires_at'=>'datetime']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function patient(): BelongsTo { return $this->belongsTo(Patient::class); } public function visit(): BelongsTo { return $this->belongsTo(Visit::class); } public function bed(): BelongsTo { return $this->belongsTo(Bed::class); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'admission_id'); } }
