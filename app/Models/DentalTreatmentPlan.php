<?php

namespace App\Models;

use App\Enums\DentalTreatmentPlanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','dental_encounter_id','patient_id','visit_id','plan_number','title','description','status','estimated_total','priority','planned_start_date','expected_completion_date','consent_required','created_by','approved_by','approved_at'])]
class DentalTreatmentPlan extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status'=>DentalTreatmentPlanStatus::class,'estimated_total'=>'decimal:2','consent_required'=>'boolean','planned_start_date'=>'date','expected_completion_date'=>'date','approved_at'=>'datetime']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); }
    public function encounter(): BelongsTo { return $this->belongsTo(DentalEncounter::class, 'dental_encounter_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function items(): HasMany { return $this->hasMany(DentalTreatmentPlanItem::class); }
}
