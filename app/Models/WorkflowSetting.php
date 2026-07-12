<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id', 'key', 'value', 'type', 'group', 'updated_by'])]
class WorkflowSetting extends Model
{
    protected function casts(): array { return ['value' => 'array']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
