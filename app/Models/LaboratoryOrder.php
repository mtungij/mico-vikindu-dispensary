<?php

namespace App\Models;

use App\Enums\ClinicalOrderStatus;
use App\Enums\ClinicalPaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'ordered_by', 'order_number', 'priority', 'clinical_notes', 'provisional_diagnosis', 'status', 'ordered_at', 'payment_status', 'completed_at', 'cancelled_at', 'cancellation_reason', 'created_by', 'updated_by'])]
class LaboratoryOrder extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status' => ClinicalOrderStatus::class, 'payment_status' => ClinicalPaymentStatus::class, 'ordered_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id'); }
    public function items(): HasMany { return $this->hasMany(LaboratoryOrderItem::class); }
    public function samples(): HasMany { return $this->hasMany(LaboratorySample::class); }
    public function results(): HasMany { return $this->hasMany(LaboratoryResult::class); }
}
