<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','visit_id','declared_by','declared_at','suspected_cause','circumstances','resuscitation_attempted','resuscitation_notes','next_of_kin_notified','notified_by','notified_at','body_released_to','notes'])]
class ObservationDeathRecord extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['declared_at'=>'datetime','notified_at'=>'datetime','resuscitation_attempted'=>'boolean','next_of_kin_notified'=>'boolean']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } }
