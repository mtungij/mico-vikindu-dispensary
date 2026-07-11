<?php

namespace App\Models;

use App\Enums\LaboratoryResultType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'service_id', 'laboratory_test_category_id', 'specimen_type_id', 'name', 'code', 'short_name', 'description', 'methodology', 'result_type', 'unit', 'default_reference_range', 'decimal_places', 'turnaround_time_minutes', 'requires_fasting', 'requires_special_consent', 'allows_multiple_specimens', 'is_panel', 'is_outsourced', 'outsourced_provider', 'critical_low', 'critical_high', 'reportable', 'is_active', 'sort_order', 'created_by', 'updated_by'])]
class LaboratoryTest extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['result_type' => LaboratoryResultType::class, 'requires_fasting' => 'boolean', 'requires_special_consent' => 'boolean', 'allows_multiple_specimens' => 'boolean', 'is_panel' => 'boolean', 'is_outsourced' => 'boolean', 'reportable' => 'boolean', 'is_active' => 'boolean', 'critical_low' => 'decimal:4', 'critical_high' => 'decimal:4']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function category(): BelongsTo { return $this->belongsTo(LaboratoryTestCategory::class, 'laboratory_test_category_id'); }
    public function specimenType(): BelongsTo { return $this->belongsTo(SpecimenType::class); }
    public function parameters(): HasMany { return $this->hasMany(LaboratoryTestParameter::class); }
    public function referenceRanges(): HasMany { return $this->hasMany(LaboratoryReferenceRange::class); }
    public function panelChildren(): HasMany { return $this->hasMany(LaboratoryTestPanel::class); }
}
