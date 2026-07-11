<?php

namespace App\Models;

use App\Enums\ObservationOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','visit_id','clinical_encounter_id','order_type','priority','instructions','ordered_by','ordered_at','scheduled_at','status','started_at','completed_at','completed_by','cancelled_at','cancelled_by','cancellation_reason','metadata','created_by','updated_by'])]
class ObservationOrder extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['status'=>ObservationOrderStatus::class,'ordered_at'=>'datetime','scheduled_at'=>'datetime','started_at'=>'datetime','completed_at'=>'datetime','cancelled_at'=>'datetime','metadata'=>'array']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } public function patient(): BelongsTo { return $this->belongsTo(Patient::class); } public function orderer(): BelongsTo { return $this->belongsTo(User::class, 'ordered_by'); } }
