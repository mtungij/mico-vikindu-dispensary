<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'facility_id', 'employee_number', 'first_name', 'middle_name', 'last_name',
    'gender', 'date_of_birth', 'marital_status', 'nationality', 'nida_number',
    'passport_number', 'primary_phone', 'secondary_phone', 'personal_email',
    'physical_address', 'postal_address', 'region', 'district', 'ward',
    'street_or_village', 'passport_photo_path', 'signature_path', 'biography',
    'emergency_notes', 'created_by', 'updated_by',
])]
class StaffProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'date_of_birth' => 'date',
            'marital_status' => MaritalStatus::class,
        ];
    }

    public function scopeForCurrentFacility(Builder $query): Builder
    {
        return $query->where('facility_id', currentFacility()?->id);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function employmentRecord(): HasOne
    {
        return $this->hasOne(EmploymentRecord::class);
    }

    public function educationRecords(): HasMany
    {
        return $this->hasMany(StaffEducationRecord::class);
    }

    public function professionalLicenses(): HasMany
    {
        return $this->hasMany(StaffProfessionalLicense::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StaffDocument::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(StaffSignature::class, 'staff_id');
    }

    public function activeSignature(): HasOne
    {
        return $this->hasOne(StaffSignature::class, 'staff_id')->where('is_active', true)->latestOfMany();
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(StaffEmergencyContact::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function fullName(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' ');
    }

    public function initials(): string
    {
        return str($this->first_name)->substr(0, 1)->append(str($this->last_name)->substr(0, 1))->upper()->toString();
    }

    public function currentAge(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function highestQualification(): ?StaffEducationRecord
    {
        return $this->educationRecords->firstWhere('is_highest_qualification', true)
            ?? $this->educationRecords()->where('is_highest_qualification', true)->first();
    }

    public function activeProfessionalLicense(): ?StaffProfessionalLicense
    {
        return $this->professionalLicenses->firstWhere('status', 'active')
            ?? $this->professionalLicenses()->where('status', 'active')->first();
    }

    public function hasExpiredLicense(): bool
    {
        return $this->professionalLicenses()->where('status', 'expired')->exists();
    }

    public function hasMissingRequiredLicense(): bool
    {
        return (bool) $this->employmentRecord?->jobTitle?->requires_professional_license
            && $this->activeProfessionalLicense() === null;
    }

    public function primaryEmergencyContact(): ?StaffEmergencyContact
    {
        return $this->emergencyContacts->firstWhere('is_primary', true)
            ?? $this->emergencyContacts()->where('is_primary', true)->first();
    }
}
