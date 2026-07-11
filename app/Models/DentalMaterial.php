<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','name','code','category','unit','description','track_inventory','medicine_id','service_id','is_active','created_by','updated_by'])]
class DentalMaterial extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['track_inventory'=>'boolean','is_active'=>'boolean']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); }
    public function medicine(): BelongsTo { return $this->belongsTo(Medicine::class); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
}
