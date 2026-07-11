<?php

namespace App\Models;

use App\Enums\ClinicalAlertStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'alert_type', 'severity', 'title', 'message', 'source_type', 'source_id', 'status', 'acknowledged_by', 'acknowledged_at', 'resolved_by', 'resolved_at'])]
class ClinicalAlert extends Model
{
    use HasFactory;
    protected function casts(): array { return ['status' => ClinicalAlertStatus::class, 'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
}
