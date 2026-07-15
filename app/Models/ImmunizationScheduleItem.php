<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImmunizationScheduleItem extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['is_required' => 'boolean']; }
    public function schedule(): BelongsTo { return $this->belongsTo(ImmunizationSchedule::class, 'immunization_schedule_id'); }
    public function vaccine(): BelongsTo { return $this->belongsTo(Vaccine::class); }
}
