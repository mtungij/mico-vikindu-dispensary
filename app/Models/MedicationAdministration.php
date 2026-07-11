<?php

namespace App\Models;

use App\Enums\MedicationAdministrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','visit_id','observation_order_id','prescription_item_id','medicine_id','medicine_name_snapshot','dose','route','frequency','scheduled_at','administered_at','administered_by','administration_status','omission_reason','refusal_reason','adverse_reaction','notes','created_by','updated_by'])]
class MedicationAdministration extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['administration_status'=>MedicationAdministrationStatus::class,'scheduled_at'=>'datetime','administered_at'=>'datetime']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } public function patient(): BelongsTo { return $this->belongsTo(Patient::class); } public function medicine(): BelongsTo { return $this->belongsTo(Medicine::class); } public function prescriptionItem(): BelongsTo { return $this->belongsTo(PrescriptionItem::class); } }
