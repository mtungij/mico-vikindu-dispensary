<?php

namespace App\Models;

use App\Enums\LaboratoryAbnormalFlag;
use App\Enums\LaboratoryResultType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['laboratory_result_id', 'laboratory_test_parameter_id', 'parameter_name_snapshot', 'parameter_code_snapshot', 'result_type', 'numeric_value', 'text_value', 'selected_value', 'boolean_value', 'unit_snapshot', 'reference_range_snapshot', 'lower_limit_snapshot', 'upper_limit_snapshot', 'abnormal_flag', 'is_critical', 'comments', 'sort_order', 'created_by', 'updated_by'])]
class LaboratoryResultValue extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['result_type' => LaboratoryResultType::class, 'abnormal_flag' => LaboratoryAbnormalFlag::class, 'numeric_value' => 'decimal:6', 'lower_limit_snapshot' => 'decimal:4', 'upper_limit_snapshot' => 'decimal:4', 'boolean_value' => 'boolean', 'is_critical' => 'boolean'];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(LaboratoryResult::class, 'laboratory_result_id');
    }

    public function displayValue(): string
    {
        return match ($this->result_type) {
            LaboratoryResultType::Numeric => rtrim(rtrim((string) $this->numeric_value, '0'), '.'),
            LaboratoryResultType::PositiveNegative => $this->selected_value === 'positive' ? 'Positive' : 'Negative',
            LaboratoryResultType::ReactiveNonReactive => $this->selected_value === 'reactive' ? 'Reactive' : 'Non-Reactive',
            LaboratoryResultType::DetectedNotDetected => $this->selected_value === 'detected' ? 'Detected' : 'Not Detected',
            LaboratoryResultType::Choice => (string) $this->selected_value,
            LaboratoryResultType::Boolean => $this->boolean_value ? 'Yes' : 'No',
            default => (string) $this->text_value,
        };
    }
}
