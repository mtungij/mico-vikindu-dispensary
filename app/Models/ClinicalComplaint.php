<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['clinical_encounter_id', 'complaint', 'duration_value', 'duration_unit', 'severity', 'notes', 'is_primary', 'created_by'])]
class ClinicalComplaint extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['is_primary' => 'boolean']; }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id'); }
}
