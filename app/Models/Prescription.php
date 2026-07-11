<?php

namespace App\Models;

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
    protected function casts(): array { return ['status' => PrescriptionStatus::class, 'prescribed_at' => 'datetime', 'dispensed_at' => 'datetime', 'cancelled_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id'); }
    public function items(): HasMany { return $this->hasMany(PrescriptionItem::class); }
    public function dispensings(): HasMany { return $this->hasMany(Dispensing::class); }
}
