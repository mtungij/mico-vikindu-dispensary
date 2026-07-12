<?php

namespace App\Models;

use App\Enums\DentalProcedureStatus;
use App\Enums\DentalProcedureType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','dental_encounter_id','patient_id','visit_id','treatment_plan_item_id','service_id','dental_procedure_type_id','procedure_number','procedure_type','tooth_number','surfaces','procedure_name_snapshot','indication','diagnosis_snapshot','anaesthesia_type','anaesthetic_used','anaesthetic_quantity','performed_by','assisted_by','started_at','completed_at','status','findings','technique_notes','complications','post_procedure_instructions','follow_up_required','follow_up_date','observation_required','invoice_item_id','created_by','updated_by'])]
class DentalProcedure extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['procedure_type'=>DentalProcedureType::class,'status'=>DentalProcedureStatus::class,'surfaces'=>'array','started_at'=>'datetime','completed_at'=>'datetime','follow_up_required'=>'boolean','observation_required'=>'boolean','follow_up_date'=>'date']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); }
    public function encounter(): BelongsTo { return $this->belongsTo(DentalEncounter::class, 'dental_encounter_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function procedureTypeCatalog(): BelongsTo { return $this->belongsTo(\App\Models\DentalProcedureType::class, 'dental_procedure_type_id'); }
    public function invoiceItem(): BelongsTo { return $this->belongsTo(InvoiceItem::class); }
    public function treatmentPlanItem(): BelongsTo { return $this->belongsTo(DentalTreatmentPlanItem::class, 'treatment_plan_item_id'); }
    public function performer(): BelongsTo { return $this->belongsTo(User::class, 'performed_by'); }
    public function materials(): HasMany { return $this->hasMany(DentalProcedureMaterial::class); }
    public function oralSurgeryDetail(): HasOne { return $this->hasOne(DentalOralSurgeryDetail::class); }
    public function restorativeDetail(): HasOne { return $this->hasOne(DentalRestorativeDetail::class); }
}
