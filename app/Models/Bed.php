<?php

namespace App\Models;

use App\Enums\BedCleaningStatus;
use App\Enums\BedStatus;
use App\Enums\BedType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_room_id','name','code','bed_type','gender_restriction','hourly_rate','session_rate','daily_rate','status','is_active','current_cleaning_status','last_cleaned_at','notes','created_by','updated_by'])]
class Bed extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['bed_type'=>BedType::class,'status'=>BedStatus::class,'current_cleaning_status'=>BedCleaningStatus::class,'hourly_rate'=>'decimal:2','session_rate'=>'decimal:2','daily_rate'=>'decimal:2','is_active'=>'boolean','last_cleaned_at'=>'datetime']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function room(): BelongsTo { return $this->belongsTo(ObservationRoom::class, 'observation_room_id'); } public function assignments(): HasMany { return $this->hasMany(BedAssignment::class); } public function activeAssignment(): HasOne { return $this->hasOne(BedAssignment::class)->where('assignment_status','active')->latestOfMany(); } public function currentAdmission(): HasOne { return $this->hasOne(ObservationAdmission::class, 'current_bed_id')->whereIn('status',['admitted','under_observation','ready_for_discharge']); } public function reservations(): HasMany { return $this->hasMany(BedReservation::class); } public function cleaningRecords(): HasMany { return $this->hasMany(BedCleaningRecord::class); } }
