<?php

namespace App\Models;

use App\Enums\EducationLevel;
use App\Enums\EmploymentCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'facility_id', 'department_id', 'name', 'code', 'description', 'employment_category',
    'requires_professional_license', 'license_authority', 'minimum_education_level',
    'is_clinical', 'is_active', 'sort_order', 'created_by', 'updated_by',
])]
class JobTitle extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'employment_category' => EmploymentCategory::class,
            'minimum_education_level' => EducationLevel::class,
            'requires_professional_license' => 'boolean',
            'is_clinical' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeForCurrentFacility(Builder $query): Builder
    {
        return $query->where('facility_id', currentFacility()?->id);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function employmentRecords(): HasMany
    {
        return $this->hasMany(EmploymentRecord::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
