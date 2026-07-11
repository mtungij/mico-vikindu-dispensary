<?php

namespace App\Models;

use App\Enums\DocumentVerificationStatus;
use App\Enums\FacilityDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'facility_id', 'document_type', 'document_name', 'document_number',
    'issue_date', 'expiry_date', 'file_path', 'verification_status', 'notes',
    'uploaded_by',
])]
class FacilityDocument extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => FacilityDocumentType::class,
            'verification_status' => DocumentVerificationStatus::class,
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
