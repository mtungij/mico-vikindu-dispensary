<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','patient_id','tooth_number','dental_encounter_id','diagnosis','canals_expected','canals_found','working_length_details','instrumentation_method','irrigation_solution','intracanal_medicament','obturation_material','status','started_at','completed_at','provider_user_id','notes'])]
class DentalEndodonticCase extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['working_length_details'=>'array','started_at'=>'datetime','completed_at'=>'datetime']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); }
    public function encounter(): BelongsTo { return $this->belongsTo(DentalEncounter::class, 'dental_encounter_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
}
