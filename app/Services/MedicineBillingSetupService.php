<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Enums\ServiceType;
use App\Models\ActivityLog;
use App\Models\Medicine;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicineBillingSetupService
{
    public function __construct(
        private readonly ServicePricingService $pricing,
        private readonly MedicineBillingReadinessService $readiness,
    ) {}

    /** @return array{medicine: Medicine, service: Service, price: ?ServicePrice, service_created: bool, price_changed: bool} */
    public function setup(Medicine $medicine, string|float|int|null $cashPrice, $actor): array
    {
        return DB::transaction(function () use ($medicine, $cashPrice, $actor): array {
            $medicine = Medicine::query()->lockForUpdate()->findOrFail($medicine->id);
            $this->authorize($medicine, $actor, $cashPrice !== null && $cashPrice !== '');

            [$service, $serviceCreated] = $this->resolveService($medicine, $actor);
            $price = null;
            $priceChanged = false;
            if ($cashPrice !== null && $cashPrice !== '') {
                [$price, $priceChanged] = $this->saveCashPrice($medicine, $service, (float) $cashPrice, $actor);
            }

            return ['medicine' => $medicine, 'service' => $service, 'price' => $price, 'service_created' => $serviceCreated, 'price_changed' => $priceChanged];
        });
    }

    public function isSystemManaged(Medicine $medicine, ?Service $service = null): bool
    {
        $service ??= $medicine->service;

        return $service !== null && str_contains((string) $service->description, $this->marker($medicine));
    }

    public function managedCode(Medicine $medicine): string
    {
        $normalized = str($medicine->code)->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->toString();
        if (strlen($normalized) <= 36) {
            return 'MED-'.$normalized;
        }

        return 'MED-'.substr($normalized, 0, 27).'-'.substr(sha1($normalized), 0, 8);
    }

    /** @return array{classification: string, risk: string, proposed_action: string, proposed_cash_price: ?float, confidence: string} */
    public function classifyForBulk(Medicine $medicine, bool $referencePriceApproved = false): array
    {
        $mappedService = $medicine->service_id
            ? Service::withTrashed()->find($medicine->service_id)
            : null;
        $referencePrice = is_numeric($medicine->default_dispensing_price) ? (float) $medicine->default_dispensing_price : null;
        $approvedPrice = $referencePriceApproved && $referencePrice !== null && $referencePrice > 0 ? $referencePrice : null;
        if (! $medicine->is_active || $medicine->trashed()) {
            return $this->classification('ambiguous_configuration', 'manual_review', 'Inactive/historical medicine; no automatic changes.', null);
        }

        if ($medicine->service_id && (! $mappedService || ! $this->isSystemManaged($medicine, $mappedService))) {
            return $this->classification(
                'historical_custom_mapping',
                'manual_review',
                'Existing historical/custom Billing Service mapping is preserved; do not restore or replace it automatically.',
                null,
            );
        }

        if ($mappedService) {
            $medicine->setRelation('service', $mappedService);
            $result = $this->readiness->inspectForPayer($medicine, $medicine->facility_id, PayerType::Cash);
            if ($result['ready']) {
                return $this->classification('already_ready', 'none', 'No action.', (float) ($result['price']?->amount ?? 0), 'authoritative_service_price');
            }

            return match ($result['code']) {
                'inactive_service' => $this->classification('inactive_service', 'manual_review', 'Reactivate or replace the existing service manually; mapping will be preserved.', null),
                'conflicting_prices' => $this->classification('conflicting_prices', 'manual_review', 'Resolve overlapping active cash prices manually.', null),
                'missing_price', 'inactive_price', 'future_price', 'expired_price' => $approvedPrice !== null
                    ? $this->classification('missing_price', 'safe_with_explicit_reference_approval', 'Create a new effective cash price version on the existing service.', $approvedPrice, 'operator_approved_reference')
                    : $this->classification($referencePrice !== null && $referencePrice <= 0 ? 'invalid_reference_price' : 'missing_price', 'manual_review', 'Enter or explicitly approve a cash price; reference price will not be used automatically.', null),
                default => $this->classification('ambiguous_configuration', 'manual_review', $result['message'], null),
            };
        }

        if ($referencePrice !== null && $referencePrice <= 0) {
            return $this->classification('invalid_reference_price', 'manual_review', 'Reference price is zero/negative; configure cash price manually.', null);
        }

        if ($this->hasDeterministicMedicineCollision($medicine)) {
            return $this->classification('ambiguous_configuration', 'manual_review', 'Another medicine resolves to the same deterministic Billing Service name or code; review all colliding medicines manually.', null);
        }

        $candidates = $this->serviceCandidates($medicine);
        if ($candidates->isNotEmpty()) {
            return $this->classification('ambiguous_configuration', 'manual_review', 'An active or historical Billing Service already uses the proposed name or code; review manually.', null);
        }

        return $approvedPrice !== null
            ? $this->classification('missing_service', 'safe_with_explicit_reference_approval', 'Create/reuse deterministic medicine service, link it, and create cash price.', $approvedPrice, 'operator_approved_reference')
            : $this->classification('missing_service', 'manual_review', 'Create/reuse deterministic service after a cash price is entered or explicitly approved.', null);
    }

    private function resolveService(Medicine $medicine, $actor): array
    {
        if ($medicine->service_id) {
            $service = Service::withTrashed()->find($medicine->service_id);
            if (! $service || $service->trashed() || ! $service->is_active) {
                throw ValidationException::withMessages(['service_id' => 'Existing Billing Service is inactive or deleted. It was not replaced automatically.']);
            }
            if ($service->facility_id !== $medicine->facility_id || $service->service_type !== ServiceType::Medicine) {
                throw ValidationException::withMessages(['service_id' => 'Existing Billing Service is incompatible or belongs to another facility. It was not replaced automatically.']);
            }

            if ($this->isSystemManaged($medicine, $service) && $service->is_active !== $medicine->is_active) {
                $old = $service->is_active;
                $service->update(['is_active' => $medicine->is_active, 'updated_by' => $actor->id]);
                $this->audit($actor, 'medicine_billing_service_status_changed', $medicine, $service, ['is_active' => $old], ['is_active' => $service->is_active]);
            }

            return [$service, false];
        }

        $candidates = $this->serviceCandidates($medicine);
        if ($candidates->count() > 1) {
            throw ValidationException::withMessages(['service_id' => 'Multiple possible Billing Services match this medicine. Select the correct service manually.']);
        }
        if ($service = $candidates->first()) {
            if ($service->trashed() || ! $service->is_active || $service->service_type !== ServiceType::Medicine) {
                throw ValidationException::withMessages(['service_id' => 'A matching Billing Service exists but is inactive or incompatible. Review it manually.']);
            }
            if (Medicine::query()->where('service_id', $service->id)->whereKeyNot($medicine->id)->exists()) {
                throw ValidationException::withMessages(['service_id' => 'The matching Billing Service is already linked to another medicine. Review it manually.']);
            }
        } else {
            $category = ServiceCategory::query()
                ->where('facility_id', $medicine->facility_id)
                ->where('code', 'PHA')
                ->where('is_active', true)
                ->first();
            if (! $category) {
                throw ValidationException::withMessages(['service_id' => 'Active Pharmacy service category (PHA) is not configured.']);
            }
            $service = Service::query()->create([
                'facility_id' => $medicine->facility_id,
                'service_category_id' => $category->id,
                'department_id' => $category->department_id,
                'name' => str($medicine->name)->limit(120, '')->toString(),
                'code' => $this->managedCode($medicine),
                'description' => $this->marker($medicine).' Automatically managed medicine billing service.',
                'service_type' => ServiceType::Medicine,
                'requires_clinical_order' => true,
                'requires_payment' => true,
                'allows_walk_in' => false,
                'stock_related' => true,
                'is_active' => $medicine->is_active,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->audit($actor, 'medicine_billing_service_created', $medicine, $service, [], $service->only(['facility_id', 'code', 'name', 'service_type', 'is_active']));
        }

        $oldServiceId = $medicine->service_id;
        $medicine->update(['service_id' => $service->id, 'updated_by' => $actor->id]);
        $this->audit($actor, 'medicine_billing_service_linked', $medicine, $service, ['service_id' => $oldServiceId], ['service_id' => $service->id]);

        return [$service, $service->wasRecentlyCreated];
    }

    private function saveCashPrice(Medicine $medicine, Service $service, float $amount, $actor): array
    {
        if ($amount < 0) {
            throw ValidationException::withMessages(['cash_price' => 'Cash price cannot be negative.']);
        }
        $applicable = $this->pricing->currentPriceQuery($service, PayerType::Cash)->get();
        if ($applicable->count() > 1) {
            throw ValidationException::withMessages(['cash_price' => 'Multiple active cash prices apply. Resolve the conflict on the Prices screen first.']);
        }
        $current = $applicable->first();
        if ($current && abs((float) $current->amount - $amount) < 0.005) {
            return [$current, false];
        }

        $price = $this->pricing->createPriceVersion($service, [
            'payer_type' => PayerType::Cash->value,
            'insurance_provider_id' => null,
            'corporate_account_id' => null,
            'amount' => $amount,
            'currency' => 'TZS',
            'effective_from' => today()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
            'notes' => 'Configured from medicine management.',
        ], $actor);
        $this->audit($actor, $current ? 'medicine_cash_price_changed' : 'medicine_cash_price_created', $medicine, $service, [
            'price_id' => $current?->id,
            'amount' => $current?->amount,
        ], [
            'price_id' => $price->id,
            'amount' => $price->amount,
            'effective_from' => $price->effective_from?->toDateString(),
        ]);

        return [$price, true];
    }

    private function serviceCandidates(Medicine $medicine)
    {
        return Service::withTrashed()
            ->where('facility_id', $medicine->facility_id)
            ->where(fn ($query) => $query
                ->where('code', $this->managedCode($medicine))
                ->orWhere('name', str($medicine->name)->limit(120, '')->toString()))
            ->get();
    }

    private function hasDeterministicMedicineCollision(Medicine $medicine): bool
    {
        $proposedName = str(str($medicine->name)->limit(120, '')->toString())->lower()->toString();
        $proposedCode = str($this->managedCode($medicine))->lower()->toString();

        return Medicine::withTrashed()
            ->where('facility_id', $medicine->facility_id)
            ->whereKeyNot($medicine->id)
            ->get(['id', 'name', 'code'])
            ->contains(function (Medicine $other) use ($proposedName, $proposedCode): bool {
                $otherName = str(str($other->name)->limit(120, '')->toString())->lower()->toString();
                $otherCode = str($this->managedCode($other))->lower()->toString();

                return $otherName === $proposedName || $otherCode === $proposedCode;
            });
    }

    private function marker(Medicine $medicine): string
    {
        return "[SYSTEM_MEDICINE:{$medicine->id}]";
    }

    private function authorize(Medicine $medicine, $actor, bool $changesPrice): void
    {
        abort_unless($actor && $actor->can('pharmacy.manage-medicines'), 403);
        abort_unless($actor->is_super_admin || $actor->staffProfile?->facility_id === $medicine->facility_id, 403);
        if ($changesPrice) {
            abort_unless($actor->can('pharmacy.manage-prices') || $actor->can('services.manage-prices'), 403);
        }
    }

    private function audit($actor, string $event, Medicine $medicine, Service $service, array $old, array $new): void
    {
        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => $event,
            'subject_type' => Medicine::class,
            'subject_id' => $medicine->id,
            'old_values' => ['facility_id' => $medicine->facility_id, 'medicine_id' => $medicine->id, 'service_id' => $service->id, ...$old],
            'new_values' => ['facility_id' => $medicine->facility_id, 'medicine_id' => $medicine->id, 'service_id' => $service->id, ...$new],
        ]);
    }

    private function classification(string $classification, string $risk, string $action, ?float $price, string $confidence = 'manual_review'): array
    {
        return ['classification' => $classification, 'risk' => $risk, 'proposed_action' => $action, 'proposed_cash_price' => $price, 'confidence' => $confidence];
    }
}
