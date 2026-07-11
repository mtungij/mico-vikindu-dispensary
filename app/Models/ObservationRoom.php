<?php

namespace App\Models;

use App\Enums\ObservationRoomType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','department_id','name','code','room_type','floor','location_description','gender_restriction','isolation_room','capacity','is_active','notes','created_by','updated_by'])]
class ObservationRoom extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['room_type'=>ObservationRoomType::class,'isolation_room'=>'boolean','capacity'=>'integer','is_active'=>'boolean']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function department(): BelongsTo { return $this->belongsTo(Department::class); } public function beds(): HasMany { return $this->hasMany(Bed::class); } public function activeAdmissions(): HasMany { return $this->hasMany(ObservationAdmission::class, 'current_room_id')->whereIn('status', ['admitted','under_observation','ready_for_discharge','awaiting_bed']); } }
