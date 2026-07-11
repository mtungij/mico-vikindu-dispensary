<?php

namespace App\Models;

use App\Enums\LaboratoryResultType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'laboratory_test_id', 'parent_parameter_id', 'name', 'code', 'description', 'result_type', 'unit', 'decimal_places', 'default_reference_range', 'critical_low', 'critical_high', 'allowed_values', 'normal_value', 'is_required', 'is_heading', 'show_on_report', 'sort_order', 'is_active', 'created_by', 'updated_by'])]
class LaboratoryTestParameter extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['result_type' => LaboratoryResultType::class, 'allowed_values' => 'array', 'is_required' => 'boolean', 'is_heading' => 'boolean', 'show_on_report' => 'boolean', 'is_active' => 'boolean', 'critical_low' => 'decimal:4', 'critical_high' => 'decimal:4']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function test(): BelongsTo { return $this->belongsTo(LaboratoryTest::class, 'laboratory_test_id'); }
}
