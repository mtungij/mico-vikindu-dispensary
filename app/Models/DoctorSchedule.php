<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id','staff_id','department_id','working_day','working_days','start_time','end_time','break_start','break_end','slot_duration','max_patients_per_day','max_patients_per_hour','unavailable_dates','is_active','created_by','updated_by'])]
class DoctorSchedule extends Model
{
    protected function casts(): array
    {
        return ['working_days' => 'array', 'unavailable_dates' => 'array', 'is_active' => 'boolean'];
    }

    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function staff(): BelongsTo { return $this->belongsTo(User::class, 'staff_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
}
