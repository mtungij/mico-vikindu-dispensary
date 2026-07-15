<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImmunizationAdministration extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['administration_date' => 'date', 'expiry_date_snapshot' => 'date', 'next_due_date' => 'date']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function child(): BelongsTo { return $this->belongsTo(RchChild::class, 'rch_child_id'); }
    public function vaccine(): BelongsTo { return $this->belongsTo(Vaccine::class); }
    public function scheduleItem(): BelongsTo { return $this->belongsTo(ImmunizationScheduleItem::class, 'immunization_schedule_item_id'); }
}
