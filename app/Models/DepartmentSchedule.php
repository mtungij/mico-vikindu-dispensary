<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id','department_id','working_day','opening_time','closing_time','lunch_start','lunch_end','slot_duration','maximum_daily_capacity','is_active','created_by','updated_by'])]
class DepartmentSchedule extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
}
