<?php

namespace App\Models;

use App\Enums\ServiceCategoryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'name', 'code', 'description', 'category_type', 'department_id', 'icon', 'color', 'sort_order', 'is_active', 'created_by', 'updated_by'])]
class ServiceCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['category_type' => ServiceCategoryType::class, 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeForCurrentFacility(Builder $query): Builder
    {
        return $query->where('facility_id', currentFacility()?->id);
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function services(): HasMany { return $this->hasMany(Service::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
