<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['patient_id', 'document_type', 'document_name', 'document_number', 'issue_date', 'expiry_date', 'file_path', 'mime_type', 'file_size', 'notes', 'uploaded_by'])]
class PatientDocument extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['issue_date' => 'date', 'expiry_date' => 'date']; }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
}
