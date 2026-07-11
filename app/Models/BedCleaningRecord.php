<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id','bed_id','observation_admission_id','cleaning_type','status','requested_at','started_at','completed_at','requested_by','cleaned_by','verified_by','notes'])]
class BedCleaningRecord extends Model { use HasFactory; protected function casts(): array { return ['requested_at'=>'datetime','started_at'=>'datetime','completed_at'=>'datetime']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function bed(): BelongsTo { return $this->belongsTo(Bed::class); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } }
