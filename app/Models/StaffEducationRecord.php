<?php

namespace App\Models;

use App\Enums\EducationLevel;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'staff_profile_id', 'education_level', 'course_name', 'institution_name', 'country',
    'start_year', 'graduation_year', 'certificate_number', 'grade_or_class',
    'description', 'certificate_document_path', 'is_highest_qualification',
    'verification_status', 'verified_by', 'verified_at', 'created_by', 'updated_by',
])]
class StaffEducationRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'education_level' => EducationLevel::class,
            'is_highest_qualification' => 'boolean',
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }
}
