<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','patient_id','dental_encounter_id','case_number','chief_concern','diagnosis','malocclusion_class','treatment_goal','appliance_type','treatment_start_date','expected_duration_months','status','assigned_dentist','notes','created_by','updated_by'])]
class OrthodonticCase extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['treatment_start_date'=>'date']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); }
    public function encounter(): BelongsTo { return $this->belongsTo(DentalEncounter::class, 'dental_encounter_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function dentist(): BelongsTo { return $this->belongsTo(User::class, 'assigned_dentist'); }
    public function visits(): HasMany { return $this->hasMany(OrthodonticVisit::class); }
    public function measurements(): HasMany { return $this->hasMany(OrthodonticMeasurement::class); }
}
