<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['patient_id', 'contact_type', 'full_name', 'relationship', 'primary_phone', 'secondary_phone', 'email', 'physical_address', 'is_primary', 'notes', 'created_by', 'updated_by'])]
class PatientContact extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['is_primary' => 'boolean']; }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
}
