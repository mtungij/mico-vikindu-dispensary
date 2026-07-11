<?php

namespace App\Models;

use App\Enums\ObservationDischargeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','visit_id','discharge_type','discharge_condition','final_diagnosis','treatment_summary','procedures_summary','medications_on_discharge','follow_up_required','follow_up_date','follow_up_department_id','discharge_instructions','warning_signs','discharged_by','authorized_by','discharged_at','billing_status','notes','created_by','updated_by'])]
class ObservationDischarge extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['discharge_type'=>ObservationDischargeType::class,'follow_up_required'=>'boolean','follow_up_date'=>'date','discharged_at'=>'datetime']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } public function patient(): BelongsTo { return $this->belongsTo(Patient::class); } public function visit(): BelongsTo { return $this->belongsTo(Visit::class); } }
