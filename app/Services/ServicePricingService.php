<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Validation\ValidationException;

class ServicePricingService
{
    public function getCurrentPrice(Service $service, PayerType $payerType, ?int $insuranceProviderId = null, ?int $corporateAccountId = null): ?ServicePrice
    {
        return ServicePrice::query()
            ->where('facility_id', $service->facility_id)
            ->where('service_id', $service->id)
            ->where('payer_type', $payerType)
            ->when($payerType === PayerType::Insurance, fn ($q) => $q->where('insurance_provider_id', $insuranceProviderId))
            ->when($payerType === PayerType::Corporate, fn ($q) => $q->where('corporate_account_id', $corporateAccountId))
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', today()))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))
            ->latest('effective_from')
            ->latest()
            ->first();
    }

    public function resolvePriceForPatient(Service $service, PayerType $payerType, ?int $insuranceProviderId = null, ?int $corporateAccountId = null): string
    {
        return $this->getCurrentPrice($service, $payerType, $insuranceProviderId, $corporateAccountId)?->amount ?? '0.00';
    }

    public function validatePriceAvailability(Service $service, PayerType $payerType, ?int $insuranceProviderId = null, ?int $corporateAccountId = null): void
    {
        if ($service->requires_payment && $this->getCurrentPrice($service, $payerType, $insuranceProviderId, $corporateAccountId) === null) {
            throw ValidationException::withMessages(['service' => 'Bei ya huduma haijasanidiwa.']);
        }
    }

    public function createPriceVersion(Service $service, array $data, $actor): ServicePrice
    {
        if ((float) $data['amount'] < 0) {
            throw ValidationException::withMessages(['amount' => 'Bei hasi hairuhusiwi.']);
        }
        if (($data['effective_from'] ?? null) && ($data['effective_to'] ?? null) && $data['effective_to'] < $data['effective_from']) {
            throw ValidationException::withMessages(['effective_to' => 'Tarehe ya mwisho haiwezi kuwa kabla ya mwanzo.']);
        }

        $payerType = PayerType::from($data['payer_type']);
        $this->deactivatePreviousPrice($service, $payerType, $data['insurance_provider_id'] ?? null, $data['corporate_account_id'] ?? null);

        return ServicePrice::query()->create([
            ...$data,
            'facility_id' => $service->facility_id,
            'service_id' => $service->id,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    public function deactivatePreviousPrice(Service $service, PayerType $payerType, ?int $insuranceProviderId = null, ?int $corporateAccountId = null): void
    {
        ServicePrice::query()
            ->where('service_id', $service->id)
            ->where('payer_type', $payerType)
            ->when($payerType === PayerType::Insurance, fn ($q) => $q->where('insurance_provider_id', $insuranceProviderId))
            ->when($payerType === PayerType::Corporate, fn ($q) => $q->where('corporate_account_id', $corporateAccountId))
            ->where('is_active', true)
            ->update(['is_active' => false, 'effective_to' => today()]);
    }

    public function getPriceHistory(Service $service) { return $service->prices()->latest()->get(); }
    public function formatMoney(string|float|int $amount, string $currency = 'TZS'): string { return $currency.' '.number_format((float) $amount, 2); }
}
