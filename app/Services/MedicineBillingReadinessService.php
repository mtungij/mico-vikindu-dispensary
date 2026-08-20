<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Enums\ServiceType;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\ServicePrice;
use App\Models\Visit;
use Illuminate\Validation\ValidationException;

class MedicineBillingReadinessService
{
    public function __construct(private readonly ServicePricingService $pricing) {}

    /** @return array{ready: bool, code: string, label: string, message: string, price: ?ServicePrice} */
    public function inspect(Medicine $medicine, Visit $visit, ?Invoice $invoice = null): array
    {
        $invoice ??= $visit->invoice;
        $payerType = $invoice?->payer_type ?? $visit->payer_type;
        $profile = $invoice?->patientPayerProfile ?? $visit->payerProfile;
        $insuranceProviderId = $invoice?->insurance_provider_id ?? $profile?->insurance_provider_id;
        $corporateAccountId = $invoice?->corporate_account_id ?? $profile?->corporate_account_id;

        return $this->inspectForPayer($medicine, $visit->facility_id, $payerType, $insuranceProviderId, $corporateAccountId);
    }

    /** @return array{ready: bool, code: string, label: string, message: string, price: ?ServicePrice} */
    public function inspectForPayer(Medicine $medicine, int $facilityId, PayerType $payerType, ?int $insuranceProviderId = null, ?int $corporateAccountId = null): array
    {

        if ($medicine->trashed() || ! $medicine->is_active) {
            return $this->failure($medicine, 'inactive_medicine', 'medicine is inactive');
        }
        if ($medicine->facility_id !== $facilityId) {
            return $this->failure($medicine, 'wrong_facility_medicine', 'medicine is not available at this facility');
        }

        $service = $medicine->service;
        if (! $service) {
            return $this->failure($medicine, 'missing_service', 'no billing service is linked');
        }
        if ($service->facility_id !== $facilityId) {
            return $this->failure($medicine, 'wrong_facility_service', 'billing service belongs to another facility');
        }
        if ($service->service_type !== ServiceType::Medicine) {
            return $this->failure($medicine, 'wrong_service_type', 'linked service is not a medicine billing service');
        }
        if (! $service->is_active) {
            return $this->failure($medicine, 'inactive_service', 'billing service is inactive');
        }
        if (! $service->requires_payment) {
            return ['ready' => true, 'code' => 'ready_free', 'label' => 'No charge', 'message' => 'Ready (no payment required)', 'price' => null];
        }

        $applicable = $this->pricing
            ->currentPriceQuery($service, $payerType, $insuranceProviderId, $corporateAccountId)
            ->get();
        if ($applicable->count() > 1) {
            return $this->failure($medicine, 'conflicting_prices', 'multiple active prices apply to this payer');
        }
        if ($price = $applicable->first()) {
            return ['ready' => true, 'code' => 'ready', 'label' => $price->currency.' '.number_format((float) $price->amount, 2), 'message' => 'Ready', 'price' => $price];
        }

        return $this->missingPriceFailure($medicine, $payerType, $insuranceProviderId, $corporateAccountId);
    }

    public function assertReady(Medicine $medicine, Visit $visit, ?Invoice $invoice = null, string $field = 'medicine_id'): array
    {
        $result = $this->inspect($medicine, $visit, $invoice);
        if (! $result['ready']) {
            throw ValidationException::withMessages([$field => $result['message']]);
        }

        return $result;
    }

    private function missingPriceFailure(Medicine $medicine, PayerType $payerType, ?int $insuranceProviderId, ?int $corporateAccountId): array
    {
        $service = $medicine->service;
        $prices = ServicePrice::withTrashed()
            ->where('service_id', $service->id)
            ->where('payer_type', $payerType)
            ->when($payerType === PayerType::Insurance, fn ($q) => $q->where('insurance_provider_id', $insuranceProviderId))
            ->when($payerType === PayerType::Corporate, fn ($q) => $q->where('corporate_account_id', $corporateAccountId))
            ->get();

        if ($prices->contains(fn ($price) => $price->facility_id !== $medicine->facility_id)) {
            return $this->failure($medicine, 'wrong_facility_price', 'price is configured for another facility');
        }
        $facilityPrices = $prices->where('facility_id', $medicine->facility_id);
        if ($facilityPrices->isNotEmpty() && $facilityPrices->every(fn ($price) => $price->trashed() || ! $price->is_active)) {
            return $this->failure($medicine, 'inactive_price', 'billing price is inactive');
        }
        if ($facilityPrices->contains(fn ($price) => $price->is_active && $price->effective_from?->isFuture())) {
            return $this->failure($medicine, 'future_price', 'billing price is not yet effective');
        }
        if ($facilityPrices->contains(fn ($price) => $price->is_active && $price->effective_to?->isPast())) {
            return $this->failure($medicine, 'expired_price', 'billing price has expired');
        }

        $payer = strtolower($payerType->label());

        return $this->failure($medicine, 'missing_price', "no active {$payer} billing price is configured");
    }

    private function failure(Medicine $medicine, string $code, string $reason): array
    {
        return [
            'ready' => false,
            'code' => $code,
            'label' => match ($code) {
                'missing_service' => 'Billing service not configured',
                'inactive_medicine' => 'Medicine inactive',
                'inactive_service' => 'Billing service inactive',
                'wrong_facility_medicine', 'wrong_facility_service', 'wrong_facility_price' => 'Wrong facility configuration',
                'wrong_service_type' => 'Invalid billing service type',
                'inactive_price' => 'Price inactive',
                'future_price' => 'Price not yet effective',
                'expired_price' => 'Price expired',
                'conflicting_prices' => 'Conflicting active prices',
                default => 'Price not configured',
            },
            'message' => "{$medicine->name} cannot currently be prescribed because {$reason}. Contact Billing/Administrator.",
            'price' => null,
        ];
    }
}
