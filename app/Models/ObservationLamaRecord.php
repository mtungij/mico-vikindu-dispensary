<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','lama_reason','counselling_notes','risks_explained','patient_or_guardian_name','witness_user_id','acknowledged_at','recorded_by'])]
class ObservationLamaRecord extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['acknowledged_at'=>'datetime']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } }
