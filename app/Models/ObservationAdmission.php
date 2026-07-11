<?php

namespace App\Models;

use App\Enums\ObservationAdmissionStatus;
use App\Enums\ObservationAdmissionType;
use App\Enums\PayerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','patient_id','visit_id','clinical_encounter_id','admission_number','admission_type','reason_for_admission','provisional_diagnosis','final_diagnosis','admitted_by','admitted_at','expected_discharge_at','actual_discharge_at','current_bed_id','current_room_id','payer_type','patient_payer_profile_id','status','acuity_level','isolation_required','guardian_required','guardian_name','guardian_phone','diet_instruction','mobility_status','fall_risk','infection_risk','allergies_snapshot','chronic_conditions_snapshot','notes','created_by','updated_by'])]
class ObservationAdmission extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['admission_type'=>ObservationAdmissionType::class,'status'=>ObservationAdmissionStatus::class,'payer_type'=>PayerType::class,'admitted_at'=>'datetime','expected_discharge_at'=>'datetime','actual_discharge_at'=>'datetime','isolation_required'=>'boolean','guardian_required'=>'boolean']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function patient(): BelongsTo { return $this->belongsTo(Patient::class); } public function visit(): BelongsTo { return $this->belongsTo(Visit::class); } public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id'); } public function bed(): BelongsTo { return $this->belongsTo(Bed::class, 'current_bed_id'); } public function room(): BelongsTo { return $this->belongsTo(ObservationRoom::class, 'current_room_id'); } public function admittingUser(): BelongsTo { return $this->belongsTo(User::class, 'admitted_by'); } public function assignments(): HasMany { return $this->hasMany(BedAssignment::class); } public function activeAssignment(): HasOne { return $this->hasOne(BedAssignment::class)->where('assignment_status','active')->latestOfMany(); } public function observations(): HasMany { return $this->hasMany(NursingObservation::class); } public function orders(): HasMany { return $this->hasMany(ObservationOrder::class); } public function medicationAdministrations(): HasMany { return $this->hasMany(MedicationAdministration::class); } public function ivFluids(): HasMany { return $this->hasMany(IvFluidAdministration::class); } public function tasks(): HasMany { return $this->hasMany(NursingTask::class); } public function handovers(): HasMany { return $this->hasMany(NursingHandover::class); } public function discharge(): HasOne { return $this->hasOne(ObservationDischarge::class); } public function cleaningRecords(): HasMany { return $this->hasMany(BedCleaningRecord::class); } public function isActive(): bool { return in_array($this->status?->value ?? $this->status, ['awaiting_payment','awaiting_bed','admitted','under_observation','ready_for_discharge'], true); } }
