<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','schedule_type','interval_minutes','next_due_at','starts_at','ends_at','instructions','is_active','created_by','updated_by'])]
class ObservationSchedule extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['next_due_at'=>'datetime','starts_at'=>'datetime','ends_at'=>'datetime','is_active'=>'boolean']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } }
