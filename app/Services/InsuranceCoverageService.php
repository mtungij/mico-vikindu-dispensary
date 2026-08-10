<?php

namespace App\Services;

use App\Models\InsuranceCoverageRule;
use App\Models\Medicine;
use App\Models\PatientInsuranceMembership;
use App\Models\Service;

class InsuranceCoverageService
{
    public function resolveServiceCoverage(PatientInsuranceMembership $membership, Service $service, float|int|string $amount = 0): array
    {
        $rule = $this->baseRuleQuery($membership)
            ->where('facility_id', $membership->facility_id)
            ->where(fn ($q) => $q
                ->where(fn ($qq) => $qq->where('rule_scope', 'service')->where('service_id', $service->id))
                ->orWhere(fn ($qq) => $qq->where('rule_scope', 'service_category')->where('service_category_id', $service->service_category_id))
                ->orWhere(fn ($qq) => $qq->where('rule_scope', 'department')->where('department_id', $service->department_id))
                ->orWhere('rule_scope', 'all'))
            ->orderByDesc('priority')
            ->first();

        return $this->resolveRule($rule, (float) $amount);
    }

    public function resolveMedicineCoverage(PatientInsuranceMembership $membership, Medicine $medicine, float|int|string $amount = 0): array
    {
        $rule = $this->baseRuleQuery($membership)
            ->where(fn ($query) => $query
                ->where(fn ($rule) => $rule->where('rule_scope', 'medicine')->where('medicine_id', $medicine->id))
                ->orWhere(fn ($rule) => $rule->where('rule_scope', 'service')->where('service_id', $medicine->service_id))
                ->orWhere(fn ($rule) => $rule->where('rule_scope', 'service_category')->where('service_category_id', $medicine->service?->service_category_id))
                ->orWhere(fn ($rule) => $rule->where('rule_scope', 'department')->where('department_id', $medicine->service?->department_id))
                ->orWhere('rule_scope', 'all'))
            ->orderByRaw("case rule_scope when 'medicine' then 1 when 'service' then 2 when 'service_category' then 3 when 'department' then 4 else 5 end")
            ->orderByDesc('priority')
            ->first();

        return $this->resolveRule($rule, (float) $amount);
    }

    private function baseRuleQuery(PatientInsuranceMembership $membership)
    {
        return InsuranceCoverageRule::query()
            ->where('facility_id', $membership->facility_id)
            ->where('insurance_provider_id', $membership->insurance_provider_id)
            ->where(fn ($query) => $query->whereNull('insurance_scheme_id')->orWhere('insurance_scheme_id', $membership->insurance_scheme_id))
            ->where(fn ($query) => $query->whereNull('benefit_package_id')->orWhere('benefit_package_id', $membership->benefit_package_id))
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', today()))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()));
    }

    private function resolveRule(?InsuranceCoverageRule $rule, float $amount): array
    {
        if (! $rule) {
            return ['coverage_status' => 'not_configured', 'covered' => false, 'patient_amount' => $amount, 'payer_amount' => 0.0, 'warnings' => [], 'blocking_errors' => ['Coverage is not configured.']];
        }

        if ($rule->coverage_status === 'excluded') {
            return ['coverage_status' => 'excluded', 'covered' => false, 'patient_amount' => $amount, 'payer_amount' => 0.0, 'warnings' => [], 'blocking_errors' => []];
        }

        $percent = (float) ($rule->coverage_percentage ?? 100);
        $payer = round($amount * ($percent / 100), 2);
        $patient = round($amount - $payer, 2);
        $copay = $this->calculateCopayment($rule, $amount);
        $patient += $copay;
        $payer = max(0, $payer - $copay);

        return [
            'coverage_status' => $rule->coverage_status,
            'covered' => in_array($rule->coverage_status, ['covered', 'partially_covered', 'authorization_required', 'referral_required'], true),
            'patient_amount' => $patient,
            'payer_amount' => $payer,
            'coverage_percentage' => $percent,
            'requires_pre_authorization' => $rule->requires_pre_authorization || $rule->coverage_status === 'authorization_required',
            'requires_referral' => $rule->requires_referral || $rule->coverage_status === 'referral_required',
            'rule_id' => $rule->id,
            'warnings' => [],
            'blocking_errors' => [],
        ];
    }

    public function calculateCopayment(InsuranceCoverageRule $rule, float $amount): float
    {
        if (! $rule->patient_copayment_type || $rule->patient_copayment_value === null) {
            return 0.0;
        }

        return $rule->patient_copayment_type === 'percentage'
            ? round($amount * ((float) $rule->patient_copayment_value / 100), 2)
            : (float) $rule->patient_copayment_value;
    }
}
