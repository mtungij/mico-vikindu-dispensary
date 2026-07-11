<?php

namespace App\Models;

use App\Enums\IvFluidStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','visit_id','observation_order_id','prescription_item_id','medicine_id','fluid_name_snapshot','volume_ml','rate_ml_per_hour','drops_per_minute','route','started_at','expected_end_at','completed_at','started_by','completed_by','status','remaining_volume_ml','reaction_notes','cannula_site','notes','created_by','updated_by'])]
class IvFluidAdministration extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['status'=>IvFluidStatus::class,'started_at'=>'datetime','expected_end_at'=>'datetime','completed_at'=>'datetime','rate_ml_per_hour'=>'decimal:2']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } public function patient(): BelongsTo { return $this->belongsTo(Patient::class); } }
