<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','observation_order_id','medication_details','started_at','completed_at','pre_spo2','post_spo2','pre_respiratory_rate','post_respiratory_rate','administered_by','response','adverse_reaction','status','notes'])]
class NebulizationRecord extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['started_at'=>'datetime','completed_at'=>'datetime','pre_spo2'=>'decimal:2','post_spo2'=>'decimal:2']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } }
