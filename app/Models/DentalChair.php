<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','dental_room_id','name','code','status','assigned_provider_id','is_active','notes'])]
class DentalChair extends Model
{
    use SoftDeletes;
    protected function casts(): array { return ['is_active'=>'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function room(): BelongsTo { return $this->belongsTo(DentalRoom::class, 'dental_room_id'); }
    public function assignedProvider(): BelongsTo { return $this->belongsTo(User::class, 'assigned_provider_id'); }
}
