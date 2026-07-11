<?php

namespace App\Models;

use App\Enums\NursingTaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','observation_order_id','task_type','title','description','priority','due_at','assigned_to_user_id','status','completed_at','completed_by','notes','created_by','updated_by'])]
class NursingTask extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['status'=>NursingTaskStatus::class,'due_at'=>'datetime','completed_at'=>'datetime']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to_user_id'); } }
