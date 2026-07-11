<?php

namespace App\Models;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'code', 'facility_type', 'ownership_type', 'registration_number',
    'operating_license_number', 'operating_license_expiry_date',
    'nhif_accreditation_number', 'nhif_contract_number', 'tin_number', 'email',
    'phone_primary', 'phone_secondary', 'website', 'postal_address',
    'physical_address', 'region', 'district', 'council', 'ward',
    'street_or_village', 'country', 'timezone', 'currency', 'currency_symbol',
    'date_format', 'time_format', 'logo_path', 'favicon_path',
    'official_stamp_path', 'receipt_header', 'receipt_footer', 'report_footer',
    'primary_color', 'secondary_color', 'default_language', 'fallback_language',
    'setup_current_step', 'setup_completed_at', 'created_by', 'updated_by',
])]
class Facility extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'facility_type' => FacilityType::class,
            'ownership_type' => OwnershipType::class,
            'operating_license_expiry_date' => 'date',
            'setup_completed_at' => 'datetime',
            'setup_current_step' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FacilityDocument::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(FacilitySetting::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function jobTitles(): HasMany
    {
        return $this->hasMany(JobTitle::class);
    }

    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }

    public function serviceCategories(): HasMany
    {
        return $this->hasMany(ServiceCategory::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isSetupCompleted(): bool
    {
        return $this->setup_completed_at !== null;
    }
}
