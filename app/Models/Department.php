<?php

namespace App\Models;

use App\Enums\DepartmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'facility_id', 'name', 'code', 'description', 'department_type', 'icon', 'color',
    'phone_extension', 'location', 'queue_enabled', 'billing_enabled',
    'clinical_department', 'stock_location_enabled', 'can_receive_patients',
    'requires_consultation', 'requires_triage', 'is_active', 'sort_order',
    'created_by', 'updated_by',
])]
class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'department_type' => DepartmentType::class,
            'queue_enabled' => 'boolean',
            'billing_enabled' => 'boolean',
            'clinical_department' => 'boolean',
            'stock_location_enabled' => 'boolean',
            'can_receive_patients' => 'boolean',
            'requires_consultation' => 'boolean',
            'requires_triage' => 'boolean',
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

    public function jobTitles(): HasMany
    {
        return $this->hasMany(JobTitle::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['is_primary', 'can_receive_queue', 'can_manage_department', 'assigned_by', 'assigned_at'])
            ->withTimestamps();
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
