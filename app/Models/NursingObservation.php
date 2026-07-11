<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','visit_id','recorded_by','recorded_at','general_condition','consciousness_level','pain_score','temperature','systolic_bp','diastolic_bp','pulse_rate','respiratory_rate','oxygen_saturation','blood_glucose','intake_summary','output_summary','mobility_status','fall_risk','skin_condition','wound_status','nausea_vomiting','bowel_status','urine_status','notes','status','created_by','updated_by'])]
class NursingObservation extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['recorded_at'=>'datetime','temperature'=>'decimal:2','oxygen_saturation'=>'decimal:2','blood_glucose'=>'decimal:2']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } public function patient(): BelongsTo { return $this->belongsTo(Patient::class); } public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); } }
