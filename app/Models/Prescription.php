<?php

namespace App\Models;

use App\Enums\ClinicalEncounterStatus;
use App\Enums\ClinicalEncounterType;
use App\Enums\PrescriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'prescribed_by', 'prescription_number', 'status', 'notes', 'prescribed_at', 'dispensed_at', 'cancelled_at', 'cancellation_reason', 'created_by', 'updated_by'])]
class Prescription extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['status' => PrescriptionStatus::class, 'prescribed_at' => 'datetime', 'dispensed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function scopeForCurrentFacility(Builder $query): Builder
    {
        return $query->where('facility_id', currentFacility()?->id);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function dispensings(): HasMany
    {
        return $this->hasMany(Dispensing::class);
    }

    public function scopeWithDispensingHandoff(Builder $query): Builder
    {
        return $query->whereHas('encounter', fn (Builder $clinical) => $clinical
            ->whereColumn('clinical_encounters.facility_id', 'prescriptions.facility_id')
            ->whereColumn('clinical_encounters.patient_id', 'prescriptions.patient_id')
            ->whereColumn('clinical_encounters.visit_id', 'prescriptions.visit_id')
            ->where(fn (Builder $eligible) => $eligible->whereNotNull('completed_at')
                ->orWhere(fn (Builder $rch) => $rch
                    ->where('encounter_type', ClinicalEncounterType::Rch)
                    ->where('status', ClinicalEncounterStatus::InProgress)
                    ->whereHas('department', fn (Builder $department) => $department->where('code', 'RCH')))))
            ->whereHas('visit', fn (Builder $visit) => $visit
                ->whereColumn('visits.facility_id', 'prescriptions.facility_id')
                ->whereColumn('visits.patient_id', 'prescriptions.patient_id')
                ->whereNotIn('visit_status', ['referred', 'cancelled', 'discharged']));
    }

    public function isEditableDraft(): bool
    {
        return $this->status === PrescriptionStatus::Draft
            && ! $this->encounter?->isReadOnly()
            && ! $this->items()->where(fn ($query) => $query->whereNotNull('invoice_item_id')->orWhere('dispensed_quantity', '>', 0))->exists()
            && ! $this->dispensings()->exists();
    }
}
