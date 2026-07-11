<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id','observation_admission_id','patient_id','from_user_id','to_user_id','shift_name','handover_at','patient_condition','pending_medications','pending_orders','iv_fluids_status','critical_alerts','special_instructions','next_observation_due_at','referral_status','discharge_plan','acknowledged_by','acknowledged_at'])]
class NursingHandover extends Model { use HasFactory; protected function casts(): array { return ['handover_at'=>'datetime','next_observation_due_at'=>'datetime','acknowledged_at'=>'datetime']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } }
