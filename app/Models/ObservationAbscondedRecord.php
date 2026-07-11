<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','last_seen_at','last_known_condition','discovered_by','actions_taken','guardian_contacted','management_notified','notes'])]
class ObservationAbscondedRecord extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['last_seen_at'=>'datetime','guardian_contacted'=>'boolean','management_notified'=>'boolean']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } }
