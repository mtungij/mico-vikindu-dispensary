<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','observation_order_id','delivery_method','flow_rate_lpm','target_spo2','started_at','ended_at','started_by','ended_by','status','pre_spo2','post_spo2','notes'])]
class OxygenTherapyRecord extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['started_at'=>'datetime','ended_at'=>'datetime','flow_rate_lpm'=>'decimal:2','pre_spo2'=>'decimal:2','post_spo2'=>'decimal:2']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } }
