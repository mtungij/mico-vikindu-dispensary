<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id', 'department_id', 'queue_prefix', 'is_active', 'print_tickets', 'display_screen_enabled', 'average_waiting_minutes', 'created_by', 'updated_by'])]
class DepartmentQueue extends Model
{
    protected function casts(): array { return ['is_active' => 'boolean', 'print_tickets' => 'boolean', 'display_screen_enabled' => 'boolean', 'average_waiting_minutes' => 'integer']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
}
