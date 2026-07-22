<?php

namespace App\Models;

use App\Enums\LaboratoryAbnormalFlag;
use App\Enums\LaboratoryResultStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'laboratory_order_id', 'laboratory_order_item_id', 'laboratory_sample_id', 'laboratory_test_id', 'result_version', 'result_status', 'overall_result', 'interpretation', 'comments', 'abnormal_flag', 'reference_range_snapshot', 'methodology_snapshot', 'entered_by', 'entered_at', 'verified_by', 'verified_at', 'released_by', 'released_at', 'reviewed_by_clinician', 'reviewed_at', 'amendment_reason', 'supersedes_result_id', 'created_by', 'updated_by'])]
class LaboratoryResult extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['result_status' => LaboratoryResultStatus::class, 'abnormal_flag' => LaboratoryAbnormalFlag::class, 'entered_at' => 'datetime', 'verified_at' => 'datetime', 'released_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function scopeForCurrentFacility(Builder $query): Builder
    {
        return $query->where('facility_id', currentFacility()?->id);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LaboratoryOrder::class, 'laboratory_order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(LaboratoryOrderItem::class, 'laboratory_order_item_id');
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(LaboratorySample::class, 'laboratory_sample_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LaboratoryTest::class, 'laboratory_test_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(LaboratoryResultValue::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
