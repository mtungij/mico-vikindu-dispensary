<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinical_encounter_id', 'field_name', 'old_value', 'new_value', 'reason', 'amended_by', 'amended_at'])]
class ClinicalNoteAmendment extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected function casts(): array { return ['amended_at' => 'datetime', 'created_at' => 'datetime']; }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id'); }
}
