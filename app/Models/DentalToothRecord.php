<?php

namespace App\Models;

use App\Enums\DentitionType;
use App\Enums\ToothStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','dental_encounter_id','patient_id','tooth_number','dentition_type','tooth_status','mobility_grade','eruption_status','periodontal_pocket_depth','gingival_recession','furcation_involvement','percussion_tenderness','vitality_status','notes','created_by','updated_by'])]
class DentalToothRecord extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['dentition_type'=>DentitionType::class,'tooth_status'=>ToothStatus::class,'periodontal_pocket_depth'=>'decimal:2','gingival_recession'=>'decimal:2']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); }
    public function encounter(): BelongsTo { return $this->belongsTo(DentalEncounter::class, 'dental_encounter_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function findings(): HasMany { return $this->hasMany(DentalToothFinding::class); }
}
