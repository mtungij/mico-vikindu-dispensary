<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','visit_id','reviewed_by','reviewed_at','current_condition','examination_findings','diagnosis_update','treatment_plan','continue_observation','ready_for_discharge','referral_required','next_review_at','notes','status','created_by','updated_by'])]
class ObservationClinicalReview extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['reviewed_at'=>'datetime','next_review_at'=>'datetime','continue_observation'=>'boolean','ready_for_discharge'=>'boolean','referral_required'=>'boolean']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } }
