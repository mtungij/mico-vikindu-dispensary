<?php

namespace App\Services;

use App\Enums\LaboratoryAbnormalFlag;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestParameter;
use App\Models\Patient;
use Illuminate\Validation\ValidationException;

class LaboratoryReferenceRangeService
{
    public function resolveForPatient(LaboratoryTest $test, Patient $patient, ?LaboratoryTestParameter $parameter = null): ?LaboratoryReferenceRange
    {
        $ageDays = $patient->date_of_birth ? $patient->date_of_birth->diffInDays(today()) : null;
        return LaboratoryReferenceRange::query()
            ->where('facility_id', $test->facility_id)
            ->where('laboratory_test_id', $test->id)
            ->when($parameter, fn ($q) => $q->where('laboratory_test_parameter_id', $parameter->id), fn ($q) => $q->whereNull('laboratory_test_parameter_id'))
            ->where('is_active', true)
            ->when($patient->gender?->value, fn ($q, $gender) => $q->where(fn ($q) => $q->whereNull('gender')->orWhere('gender', $gender)))
            ->when($ageDays !== null, fn ($q) => $q->where(fn ($q) => $q->whereNull('minimum_age_days')->orWhere('minimum_age_days', '<=', $ageDays))->where(fn ($q) => $q->whereNull('maximum_age_days')->orWhere('maximum_age_days', '>=', $ageDays)))
            ->orderByDesc('priority')
            ->first();
    }

    public function resolveForParameter(LaboratoryTestParameter $parameter, Patient $patient): ?LaboratoryReferenceRange
    {
        return $this->resolveForPatient($parameter->test, $patient, $parameter);
    }

    public function validateOverlaps(array $data): void
    {
        if (($data['minimum_age_days'] ?? null) !== null && ($data['maximum_age_days'] ?? null) !== null && $data['minimum_age_days'] > $data['maximum_age_days']) {
            throw ValidationException::withMessages(['maximum_age_days' => 'Maximum age haiwezi kuwa chini ya minimum age.']);
        }
    }

    public function formatRange(?LaboratoryReferenceRange $range, ?LaboratoryTestParameter $parameter = null, ?LaboratoryTest $test = null): ?string
    {
        if ($range?->textual_range) {
            return $range->textual_range;
        }
        if ($range && ($range->lower_limit !== null || $range->upper_limit !== null)) {
            return trim(($range->lower_limit ?? '').' - '.($range->upper_limit ?? '').' '.($range->unit ?? ''));
        }
        return $parameter?->default_reference_range ?? $test?->default_reference_range;
    }

    public function classifyResult(?float $value, ?float $lower, ?float $upper, ?float $criticalLow = null, ?float $criticalHigh = null): LaboratoryAbnormalFlag
    {
        if ($value === null) {
            return LaboratoryAbnormalFlag::Indeterminate;
        }
        if ($criticalLow !== null && $value <= $criticalLow) {
            return LaboratoryAbnormalFlag::CriticalLow;
        }
        if ($criticalHigh !== null && $value >= $criticalHigh) {
            return LaboratoryAbnormalFlag::CriticalHigh;
        }
        if ($lower !== null && $value < $lower) {
            return LaboratoryAbnormalFlag::Low;
        }
        if ($upper !== null && $value > $upper) {
            return LaboratoryAbnormalFlag::High;
        }
        return LaboratoryAbnormalFlag::Normal;
    }

    public function determineAbnormalFlag(?float $value, ?LaboratoryReferenceRange $range, ?LaboratoryTestParameter $parameter = null, ?LaboratoryTest $test = null): LaboratoryAbnormalFlag
    {
        return $this->classifyResult($value, $range?->lower_limit ? (float) $range->lower_limit : null, $range?->upper_limit ? (float) $range->upper_limit : null, $parameter?->critical_low ? (float) $parameter->critical_low : ($test?->critical_low ? (float) $test->critical_low : null), $parameter?->critical_high ? (float) $parameter->critical_high : ($test?->critical_high ? (float) $test->critical_high : null));
    }
}
