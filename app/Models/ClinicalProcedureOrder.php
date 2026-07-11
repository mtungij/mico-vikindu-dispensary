<?php

namespace App\Models;

use App\Enums\ProcedureOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'service_id', 'ordered_by', 'procedure_name_snapshot', 'instructions', 'priority', 'status', 'scheduled_at', 'performed_at', 'performed_by', 'invoice_item_id', 'notes', 'created_by', 'updated_by'])]
class ClinicalProcedureOrder extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status' => ProcedureOrderStatus::class, 'scheduled_at' => 'datetime', 'performed_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id'); }
}
