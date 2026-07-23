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
        private readonly LaboratoryOrderStatusService $orderStatuses,
    ) {}

    public function saveForItem(LaboratoryOrderItem $item, array $values, $actor, bool $submit = false): LaboratoryResult
    {
        return DB::transaction(function () use ($item, $values, $actor, $submit): LaboratoryResult {
            $item = LaboratoryOrderItem::query()->with(['order.patient', 'laboratoryTest.parameters', 'sample'])->lockForUpdate()->findOrFail($item->id);
            $this->authorizeEntry($item, $actor, $submit);
            $result = $this->draftForLockedItem($item, $actor);

            return $this->persistValues($result, $values, $actor, $submit);
        });
    }

    public function createDraft(LaboratoryOrderItem $item, $actor): LaboratoryResult
    {
        return DB::transaction(function () use ($item, $actor): LaboratoryResult {
            $item = LaboratoryOrderItem::query()->with(['order', 'laboratoryTest', 'sample'])->lockForUpdate()->findOrFail($item->id);
            $this->authorizeEntry($item, $actor, false);

            return $this->draftForLockedItem($item, $actor);
        });
    }

    public function saveValues(LaboratoryResult $result, array $values, $actor, bool $submit = false): LaboratoryResult
    {
        return DB::transaction(function () use ($result, $values, $actor, $submit): LaboratoryResult {
            $result = LaboratoryResult::query()->with(['orderItem.order', 'orderItem.sample', 'test.parameters'])->lockForUpdate()->findOrFail($result->id);
            $this->authorizeEntry($result->orderItem, $actor, $submit);

            return $this->persistValues($result, $values, $actor, $submit);
        });
    }

    public function submitForVerification(LaboratoryResult $result, $actor): LaboratoryResult
    {
        return DB::transaction(function () use ($result, $actor): LaboratoryResult {
            $result = LaboratoryResult::query()->with(['orderItem.order', 'orderItem.sample'])->lockForUpdate()->findOrFail($result->id);
            $this->authorizeEntry($result->orderItem, $actor, true);
            if ($result->result_status === LaboratoryResultStatus::PendingVerification) {
                throw ValidationException::withMessages(['result' => 'Matokeo haya tayari yametumwa kwa uthibitisho.']);
            }
            if (! $result->values()->exists()) {
                throw ValidationException::withMessages(['values' => 'Result haina values.']);
            }
            $result->update(['result_status' => LaboratoryResultStatus::PendingVerification, 'updated_by' => $actor->id]);
            $result->orderItem->update(['result_status' => LaboratoryResultStatus::PendingVerification->value]);
            $this->orderStatuses->recalculate($result->orderItem->order, $actor);
            $this->audit($actor, 'result_submitted', $result);

            return $result->refresh();
        });
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
        $this->audit($actor, 'result_marked_error', $result);

        return $result->refresh();
    }

    private function authorizeEntry(LaboratoryOrderItem $item, $actor, bool $submit): void
    {
        abort_unless($actor->can('laboratory-results.enter'), 403, 'Huna ruhusa ya kuingiza matokeo.');
        if ($submit) {
            abort_unless($actor->can('laboratory-results.submit'), 403, 'Huna ruhusa ya kutuma matokeo kwa uthibitisho.');
        }
        abort_unless(
            $item->order->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(),
            403,
            'Order hii ni ya facility nyingine.',
        );
        $this->paymentGuard->ensureProcessable($item->order, $actor, $submit ? 'submit_results' : 'result_entry');

        if (! $item->laboratory_test_id) {
            throw ValidationException::withMessages(['laboratory_test_id' => 'Order item haina configured laboratory test.']);
        }
        if ($item->status === 'cancelled') {
            throw ValidationException::withMessages(['item' => 'Kipimo hiki kimefutwa.']);
        }
        if (! $item->sample || $item->sample->sample_status !== LaboratorySampleStatus::Accepted) {
            throw ValidationException::withMessages(['sample' => 'Sampuli haijakubaliwa bado.']);
        }
    }

    private function draftForLockedItem(LaboratoryOrderItem $item, $actor): LaboratoryResult
    {
        $submitted = LaboratoryResult::query()
            ->where('laboratory_order_item_id', $item->id)
            ->whereIn('result_status', [
                LaboratoryResultStatus::PendingVerification->value,
                LaboratoryResultStatus::Verified->value,
                LaboratoryResultStatus::Released->value,
            ])->lockForUpdate()->first();
        if ($submitted) {
            throw ValidationException::withMessages(['result' => 'Matokeo haya tayari yametumwa kwa uthibitisho.']);
        }

        $draft = LaboratoryResult::query()
            ->where('laboratory_order_item_id', $item->id)
            ->whereIn('result_status', [LaboratoryResultStatus::Draft->value, LaboratoryResultStatus::Entered->value])
            ->lockForUpdate()
            ->latest('result_version')
            ->first();

        return $draft ?: LaboratoryResult::query()->create([
            'facility_id' => $item->order->facility_id,
            'laboratory_order_id' => $item->laboratory_order_id,
            'laboratory_order_item_id' => $item->id,
            'laboratory_sample_id' => $item->sample_id,
            'laboratory_test_id' => $item->laboratory_test_id,
            'result_version' => (int) LaboratoryResult::query()->where('laboratory_order_item_id', $item->id)->max('result_version') + 1,
            'result_status' => LaboratoryResultStatus::Draft,
            'methodology_snapshot' => $item->laboratoryTest?->methodology,
            'created_by' => $actor->id,
        ]);
    }

    private function persistValues(LaboratoryResult $result, array $values, $actor, bool $submit): LaboratoryResult
    {
        if (! in_array($result->result_status, [LaboratoryResultStatus::Draft, LaboratoryResultStatus::Entered], true)) {
            throw ValidationException::withMessages(['result' => 'Matokeo haya tayari yametumwa kwa uthibitisho.']);
        }

        $test = $result->test()->with('parameters')->firstOrFail();
        $parameters = $test->parameters()->where('is_active', true)->where('is_heading', false)->orderBy('sort_order')->get();
        if ($parameters->isEmpty()) {
            $parameters = collect([(object) ['id' => null, 'name' => $test->name, 'code' => $test->code, 'result_type' => $test->result_type, 'unit' => $test->unit, 'default_reference_range' => $test->default_reference_range, 'critical_low' => $test->critical_low, 'critical_high' => $test->critical_high, 'allowed_values' => null, 'sort_order' => 0, 'is_required' => true]]);
        }

        $prepared = [];
        foreach ($parameters as $parameter) {
            $key = (string) ($parameter->id ?? 'main');
            $payload = $values[$key] ?? [];
            $raw = $payload['value'] ?? null;
            if (($parameter->is_required ?? true) && blank($raw)) {
                throw ValidationException::withMessages(["values.$key.value" => "Tafadhali ingiza matokeo ya {$parameter->name}."]);
            }
            $type = $parameter->result_type instanceof LaboratoryResultType
                ? $parameter->result_type
                : LaboratoryResultType::from($parameter->result_type->value ?? $parameter->result_type);
            $this->validateRawValue($type, $raw, $parameter, $key);
            $range = $parameter->id
                ? $this->ranges->resolveForParameter($parameter, $result->order->patient)
                : $this->ranges->resolveForPatient($test, $result->order->patient);
            $prepared[] = $this->buildValueData($type, $raw, $parameter, $range, $actor);
        }

        $result->values()->delete();
        $overallFlags = [];
        foreach ($prepared as $data) {
            $value = $result->values()->create($data);
            $overallFlags[] = $value->abnormal_flag?->value ?? LaboratoryAbnormalFlag::Indeterminate->value;
            if ($value->is_critical) {
                $this->criticalResults->createAlert($result, $value, $actor);
            }
        }

        $status = $submit ? LaboratoryResultStatus::PendingVerification : LaboratoryResultStatus::Entered;
        $result->update([
            'result_status' => $status,
            'entered_by' => $actor->id,
            'entered_at' => now(),
            'abnormal_flag' => $this->overallFlag($overallFlags),
            'overall_result' => $values['overall_result'] ?? null,
            'comments' => $values['comments'] ?? null,
            'updated_by' => $actor->id,
        ]);
        $result->orderItem->update([
            'result_status' => $status->value,
            'result_entered_at' => now(),
        ]);
        if ($submit) {
            $this->orderStatuses->recalculate($result->orderItem->order, $actor);
        }
        $this->audit($actor, $submit ? 'result_submitted' : 'result_draft_saved', $result);

        return $result->refresh();
    }

    private function validateRawValue(LaboratoryResultType $type, mixed $raw, mixed $parameter, string $key): void
    {
        if ($type === LaboratoryResultType::Numeric && ! is_numeric($raw)) {
            throw ValidationException::withMessages(["values.$key.value" => "Thamani ya {$parameter->name} lazima iwe namba."]);
        }
        $allowed = match ($type) {
            LaboratoryResultType::PositiveNegative => ['positive', 'negative'],
            LaboratoryResultType::ReactiveNonReactive => ['reactive', 'non_reactive'],
            LaboratoryResultType::DetectedNotDetected => ['detected', 'not_detected'],
            LaboratoryResultType::Choice => $parameter->allowed_values ?? [],
            default => [],
        };
        if ($allowed !== [] && ! in_array($raw, $allowed, true)) {
            throw ValidationException::withMessages(["values.$key.value" => "Tafadhali chagua thamani halali ya {$parameter->name}."]);
        }
    }

    private function buildValueData(LaboratoryResultType $type, mixed $raw, mixed $parameter, mixed $range, $actor): array
    {
        $numeric = $type === LaboratoryResultType::Numeric ? (float) $raw : null;
        $flag = $numeric !== null ? $this->ranges->determineAbnormalFlag($numeric, $range, $parameter->id ? $parameter : null) : LaboratoryAbnormalFlag::Normal;
        $critical = in_array($flag, [LaboratoryAbnormalFlag::CriticalLow, LaboratoryAbnormalFlag::CriticalHigh, LaboratoryAbnormalFlag::Critical], true);

        return [
            'laboratory_test_parameter_id' => $parameter->id,
            'parameter_name_snapshot' => $parameter->name,
            'parameter_code_snapshot' => $parameter->code,
            'result_type' => $type,
            'numeric_value' => $numeric,
            'text_value' => in_array($type, [LaboratoryResultType::Text, LaboratoryResultType::LongText, LaboratoryResultType::Date, LaboratoryResultType::Time, LaboratoryResultType::Other], true) ? $raw : null,
            'selected_value' => in_array($type, [LaboratoryResultType::PositiveNegative, LaboratoryResultType::ReactiveNonReactive, LaboratoryResultType::DetectedNotDetected, LaboratoryResultType::Choice], true) ? $raw : null,
            'boolean_value' => $type === LaboratoryResultType::Boolean ? filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) : null,
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

    private function audit($actor, string $event, LaboratoryResult $result): void
    {
        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => $event,
            'subject_type' => $result::class,
            'subject_id' => $result->id,
            'new_values' => [
                'facility_id' => $result->facility_id,
                'laboratory_order_id' => $result->laboratory_order_id,
                'laboratory_order_item_id' => $result->laboratory_order_item_id,
                'result_status' => $result->result_status->value,
            ],
        ]);
    }
}
