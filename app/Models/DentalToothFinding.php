<?php

namespace App\Models;

use App\Enums\DentalFindingStatus;
use App\Enums\ToothSurface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','dental_tooth_record_id','dental_encounter_id','finding_type_id','surface','severity','finding_status','description','diagnosed_by','diagnosed_at','resolved_at','supersedes_finding_id','created_by','updated_by'])]
class DentalToothFinding extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['surface'=>ToothSurface::class,'finding_status'=>DentalFindingStatus::class,'diagnosed_at'=>'datetime','resolved_at'=>'datetime']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); }
    public function toothRecord(): BelongsTo { return $this->belongsTo(DentalToothRecord::class, 'dental_tooth_record_id'); }
    public function encounter(): BelongsTo { return $this->belongsTo(DentalEncounter::class, 'dental_encounter_id'); }
    public function type(): BelongsTo { return $this->belongsTo(DentalFindingType::class, 'finding_type_id'); }
}
