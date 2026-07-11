<?php

namespace App\Models;

use App\Enums\ProfessionalLicenseStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'staff_profile_id', 'license_type', 'professional_body', 'registration_number',
    'license_number', 'issue_date', 'expiry_date', 'status', 'document_path',
    'notes', 'verification_status', 'verified_by', 'verified_at', 'created_by', 'updated_by',
])]
class StaffProfessionalLicense extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'status' => ProfessionalLicenseStatus::class,
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }
}
