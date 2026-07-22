<?php

namespace App\Services;

use App\Enums\LaboratoryAbnormalFlag;
use App\Enums\LaboratoryResultStatus;
use App\Enums\LaboratoryResultType;
use App\Enums\LaboratorySampleStatus;
use App\Models\ActivityLog;
use App\Models\LaboratoryOrderItem;
use App\Models\LaboratoryResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryResultService
{
    public function __construct(
        private readonly LaboratoryReferenceRangeService $ranges,
        private readonly LaboratoryCriticalResultService $criticalResults,
        private readonly LaboratoryPaymentGuard $paymentGuard,
    ) {}

    public function createDraft(LaboratoryOrderItem $item, $actor): LaboratoryResult
    {
        $this->paymentGuard->ensureProcessable($item->order, $actor, 'result_entry');

        if (! $item->laboratory_test_id) {
            throw ValidationException::withMessages(['laboratory_test_id' => 'Order item haina configured laboratory test.']);
        }
        if (! $item->sample || $item->sample->sample_status !== LaboratorySampleStatus::Accepted) {
            throw ValidationException::withMessages(['sample' => 'Sample lazima iwe accepted kabla ya kuweka results.']);
        }

        return LaboratoryResult::query()->firstOrCreate([
            'laboratory_order_item_id' => $item->id,
            'result_status' => LaboratoryResultStatus::Draft,
        ], [
            'facility_id' => $item->order->facility_id,
            'laboratory_order_id' => $item->laboratory_order_id,
            'laboratory_sample_id' => $item->sample_id,
            'laboratory_test_id' => $item->laboratory_test_id,
            'result_version' => (int) LaboratoryResult::query()->where('laboratory_order_item_id', $item->id)->max('result_version') + 1,
            'methodology_snapshot' => $item->laboratoryTest?->methodology,
            'created_by' => $actor->id,
        ]);
    }

    public function saveValues(LaboratoryResult $result, array $values, $actor, bool $submit = false): LaboratoryResult
    {
        return DB::transaction(function () use ($result, $values, $actor, $submit) {
            if (! in_array($result->result_status, [LaboratoryResultStatus::Draft, LaboratoryResultStatus::Entered], true)) {
                throw ValidationException::withMessages(['result' => 'Result haiwezi kubadilishwa kwenye status hii.']);
            }
            $test = $result->test()->with('parameters')->firstOrFail();
            $parameters = $test->parameters()->where('is_active', true)->where('is_heading', false)->orderBy('sort_order')->get();
            if ($parameters->isEmpty()) {
                $parameters = collect([(object) ['id' => null, 'name' => $test->name, 'code' => $test->code, 'result_type' => $test->result_type, 'unit' => $test->unit, 'default_reference_range' => $test->default_reference_range, 'critical_low' => $test->critical_low, 'critical_high' => $test->critical_high, 'allowed_values' => null, 'sort_order' => 0, 'is_required' => true]]);
            }

            $result->values()->delete();
            $overallFlags = [];
            foreach ($parameters as $parameter) {
                $key = (string) ($parameter->id ?? 'main');
                $payload = $values[$key] ?? [];
                if (($parameter->is_required ?? true) && blank($payload['value'] ?? null)) {
                    throw ValidationException::withMessages(["values.$key" => "{$parameter->name} inahitajika."]);
                }
                $range = $parameter->id ? $this->ranges->resolveForParameter($parameter, $result->order->patient) : $this->ranges->resolveForPatient($test, $result->order->patient);
                $type = $parameter->result_type instanceof LaboratoryResultType ? $parameter->result_type : LaboratoryResultType::from($parameter->result_type->value ?? $parameter->result_type);
                $data = $this->buildValueData($type, $payload['value'] ?? null, $parameter, $range, $actor);
                $value = $result->values()->create($data);
                $overallFlags[] = $value->abnormal_flag?->value ?? LaboratoryAbnormalFlag::Indeterminate->value;
                if ($value->is_critical) {
                    $this->criticalResults->createAlert($result, $value, $actor);
                }
            }
            $flag = $this->overallFlag($overallFlags);
            $result->update(['result_status' => $submit ? LaboratoryResultStatus::PendingVerification : LaboratoryResultStatus::Entered, 'entered_by' => $actor->id, 'entered_at' => now(), 'abnormal_flag' => $flag, 'overall_result' => $values['overall_result'] ?? null, 'comments' => $values['comments'] ?? null, 'updated_by' => $actor->id]);
            $result->orderItem->update(['result_status' => $result->result_status->value, 'result_entered_at' => now()]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => $submit ? 'result_submitted' : 'result_draft_saved', 'subject_type' => $result::class, 'subject_id' => $result->id]);

            return $result->refresh();
        });
    }

    public function submitForVerification(LaboratoryResult $result, $actor): LaboratoryResult
    {
        if (! $result->values()->exists()) {
            throw ValidationException::withMessages(['values' => 'Result haina values.']);
        }
        $result->update(['result_status' => LaboratoryResultStatus::PendingVerification, 'updated_by' => $actor->id]);
        $result->orderItem->update(['result_status' => 'pending_verification']);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'result_submitted', 'subject_type' => $result::class, 'subject_id' => $result->id]);

        return $result->refresh();
    }

    public function getLatestResult(LaboratoryOrderItem $item): ?LaboratoryResult
    {
        return LaboratoryResult::query()->where('laboratory_order_item_id', $item->id)->latest('result_version')->first();
    }

    public function markEnteredInError(LaboratoryResult $result, string $reason, $actor): LaboratoryResult
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu inahitajika.']);
        }
        $result->update(['result_status' => LaboratoryResultStatus::EnteredInError, 'amendment_reason' => $reason, 'updated_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'result_marked_error', 'subject_type' => $result::class, 'subject_id' => $result->id]);

        return $result->refresh();
    }

    private function buildValueData(LaboratoryResultType $type, mixed $raw, mixed $parameter, mixed $range, $actor): array
    {
        $numeric = $type === LaboratoryResultType::Numeric && $raw !== null ? (float) $raw : null;
        $flag = $numeric !== null ? $this->ranges->determineAbnormalFlag($numeric, $range, $parameter->id ? $parameter : null) : LaboratoryAbnormalFlag::Normal;
        $critical = in_array($flag, [LaboratoryAbnormalFlag::CriticalLow, LaboratoryAbnormalFlag::CriticalHigh, LaboratoryAbnormalFlag::Critical], true);
        if ($type === LaboratoryResultType::Choice && $parameter->allowed_values && ! in_array($raw, $parameter->allowed_values, true)) {
            throw ValidationException::withMessages(['values' => 'Choice value hairuhusiwi.']);
        }

        return [
            'laboratory_test_parameter_id' => $parameter->id,
            'parameter_name_snapshot' => $parameter->name,
            'parameter_code_snapshot' => $parameter->code,
            'result_type' => $type,
            'numeric_value' => $numeric,
            'text_value' => in_array($type, [LaboratoryResultType::Text, LaboratoryResultType::LongText], true) ? $raw : null,
            'selected_value' => in_array($type, [LaboratoryResultType::PositiveNegative, LaboratoryResultType::ReactiveNonReactive, LaboratoryResultType::DetectedNotDetected, LaboratoryResultType::Choice], true) ? $raw : null,
            'boolean_value' => $type === LaboratoryResultType::Boolean ? (bool) $raw : null,
            'unit_snapshot' => $parameter->unit,
            'reference_range_snapshot' => $this->ranges->formatRange($range, $parameter->id ? $parameter : null),
            'lower_limit_snapshot' => $range?->lower_limit,
            'upper_limit_snapshot' => $range?->upper_limit,
            'abnormal_flag' => $flag,
            'is_critical' => $critical,
            'sort_order' => $parameter->sort_order ?? 0,
            'created_by' => $actor->id,
        ];
    }

    private function overallFlag(array $flags): LaboratoryAbnormalFlag
    {
        if (array_intersect($flags, ['critical', 'critical_low', 'critical_high'])) {
            return LaboratoryAbnormalFlag::Critical;
        }
        if (array_intersect($flags, ['low', 'high', 'abnormal'])) {
            return LaboratoryAbnormalFlag::Abnormal;
        }

        return LaboratoryAbnormalFlag::Normal;
    }
}
