<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCurrentFacility
{
    public function scopeForCurrentFacility(Builder $query): Builder
    {
        return $query->where('facility_id', currentFacility()?->id);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Facility::class);
    }
}
