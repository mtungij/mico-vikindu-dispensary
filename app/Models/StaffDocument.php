<?php

namespace App\Models;

use App\Enums\StaffDocumentType;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'staff_profile_id', 'document_type', 'document_name', 'document_number',
    'issue_date', 'expiry_date', 'file_path', 'mime_type', 'file_size',
    'verification_status', 'notes', 'uploaded_by', 'verified_by', 'verified_at',
])]
class StaffDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'document_type' => StaffDocumentType::class,
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
