<?php

namespace App\Services;

use App\Enums\ClinicalOrderStatus;
use App\Enums\ClinicalPaymentStatus;
use App\Enums\PayerType;
use App\Enums\ServiceType;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryTest;
use App\Models\Service;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryOrderService
{
    public function __construct(
        private readonly SequenceNumberService $numbers,
        private readonly InvoiceService $invoices,
        private readonly BillingChargeService $charges,
        private readonly InvoiceStatusService $invoiceStatuses,
        private readonly LaboratoryCoverageService $coverage,
        private readonly WorkflowService $workflow,
    ) {}

    public function generateOrderNumber(int $facilityId): string
    {
        return $this->numbers->next('laboratory_order_number_sequences', $facilityId, 'LAB', 6);
    }

    public function createOrder(ClinicalEncounter $encounter, array $data, $actor): LaboratoryOrder
    {
        return DB::transaction(function () use ($encounter, $data, $actor) {
            $services = $this->validatedServices($encounter, $data['service_ids'] ?? []);
            $this->coverage->ensureApproved($encounter, $services);
            $this->ensureNoDuplicateActiveTests($encounter, $services);

            $paymentStatus = $this->resolvePaymentStatus($encounter);
            $status = $paymentStatus === ClinicalPaymentStatus::Pending ? ClinicalOrderStatus::AwaitingPayment : ClinicalOrderStatus::Ordered;
            $order = LaboratoryOrder::query()->create([
                'facility_id' => $encounter->facility_id,
                'patient_id' => $encounter->patient_id,
                'visit_id' => $encounter->visit_id,
                'clinical_encounter_id' => $encounter->id,
                'source' => LaboratoryOrder::SOURCE_OPD,
                'ordered_by' => $actor->id,
                'order_number' => $this->generateOrderNumber($encounter->facility_id),
                'priority' => $data['priority'] ?? 'normal',
                'clinical_notes' => $data['clinical_notes'] ?? null,
                'provisional_diagnosis' => $data['provisional_diagnosis'] ?? null,
                'status' => $status,
                'ordered_at' => now(),
                'payment_status' => $paymentStatus,
                'created_by' => $actor->id,
            ]);
            $invoice = $this->resolveInvoice($order, $actor);
            $this->addItems($order, $invoice, $services, $actor);
            $invoice = $this->invoiceStatuses->recalculate($invoice);
            if ((float) $invoice->balance_amount <= 0) {
                $this->activateLaboratoryQueue($order, $actor);
            } else {
                $billing = Department::query()
                    ->where('facility_id', $order->facility_id)
                    ->where('code', 'BIL')
                    ->where('is_active', true)
                    ->first();
                if ($billing) {
                    $billingQueue = $this->workflow->createQueue(
                        $order->visit,
                        $billing,
                        $actor,
                        VisitStatus::AwaitingPayment,
                        'Laboratory payment required',
                        true,
                        false,
                    );
                    $this->workflow->updateVisitStatus(
                        $order->visit->refresh(),
                        VisitStatus::AwaitingPayment,
                        $actor,
                        $billingQueue,
                    );
                }
            }

            $this->audit($actor, 'lab_order_created', $order, [
                'facility_id' => $order->facility_id,
                'visit_id' => $order->visit_id,
                'invoice_id' => $invoice->id,
                'payment_status' => $order->payment_status->value,
            ]);
            $this->audit($actor, 'laboratory_ordered', $order, [
                'facility_id' => $order->facility_id,
                'visit_id' => $order->visit_id,
                'invoice_id' => $invoice->id,
                'laboratory_order_id' => $order->id,
                'payment_status' => $order->payment_status->value,
            ]);
            $this->audit($actor, 'laboratory_invoice_updated', $invoice, [
                'facility_id' => $invoice->facility_id,
                'visit_id' => $invoice->visit_id,
                'laboratory_order_id' => $order->id,
                'balance_amount' => (float) $invoice->balance_amount,
            ]);

            return $order->refresh();
        });
    }

    /** @param array<int, int|string> $serviceIds */
    public function createDirectOrder(Visit $visit, Invoice $invoice, array $serviceIds, $actor): LaboratoryOrder
    {
        abort_unless(
            $actor->can('laboratory-tests.view'),
            403,
            'Huna ruhusa ya kuangalia vipimo vya maabara kwa usajili wa Direct Laboratory.',
        );
        abort_unless(
            $actor->can('laboratory-orders.create-direct'),
            403,
            'Huna ruhusa ya kuunda direct laboratory order.',
        );

        return DB::transaction(function () use ($visit, $invoice, $serviceIds, $actor): LaboratoryOrder {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $services = $this->validatedServicesForFacility($visit->facility_id, $serviceIds);
            $serviceKey = $services->pluck('id')->sort()->implode(',');

            $existing = LaboratoryOrder::query()
                ->where('visit_id', $visit->id)
                ->where('source', LaboratoryOrder::SOURCE_RECEPTION_DIRECT)
                ->whereNotIn('status', [ClinicalOrderStatus::Cancelled->value])
                ->whereHas('items', fn ($query) => $query->whereIn('service_id', $services->pluck('id')))
                ->with('items')
                ->lockForUpdate()
                ->first();
            if ($existing && $existing->items->pluck('service_id')->sort()->implode(',') === $serviceKey) {
                return $existing;
            }

            $paymentStatus = $this->resolveVisitPaymentStatus($visit);
            $order = LaboratoryOrder::query()->create([
                'facility_id' => $visit->facility_id,
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'clinical_encounter_id' => null,
                'source' => LaboratoryOrder::SOURCE_RECEPTION_DIRECT,
                'ordered_by' => $actor->id,
                'order_number' => $this->generateOrderNumber($visit->facility_id),
                'priority' => $visit->priority?->value ?? $visit->priority ?? 'normal',
                'clinical_notes' => $visit->reason_for_visit,
                'status' => $paymentStatus === ClinicalPaymentStatus::Pending
                    ? ClinicalOrderStatus::AwaitingPayment
                    : ClinicalOrderStatus::Ordered,
                'ordered_at' => now(),
                'payment_status' => $paymentStatus,
                'created_by' => $actor->id,
            ]);
            $this->addItems($order, $invoice, $services, $actor);
            $invoice = $this->invoiceStatuses->recalculate($invoice);

            $this->audit($actor, 'direct_laboratory_order_created', $order, [
                'facility_id' => $order->facility_id,
                'visit_id' => $order->visit_id,
                'invoice_id' => $invoice->id,
                'service_ids' => $services->pluck('id')->all(),
            ]);

            return $order->refresh();
        });
    }

    /** @param Collection<int, Service> $services */
    public function addItems(LaboratoryOrder $order, Invoice $invoice, Collection $services, $actor): void
    {
        foreach ($services as $service) {
            $item = $this->charges->addServiceCharge($invoice, $service, $actor, $order, 1, [
                'source' => 'laboratory',
                'laboratory_order_id' => $order->id,
                'ordering_clinician_id' => $order->ordered_by,
            ]);
            $test = LaboratoryTest::query()->where('facility_id', $order->facility_id)->where('service_id', $service->id)->first();
            $orderItem = $order->items()->firstOrCreate([
                'service_id' => $service->id,
            ], [
                'service_id' => $service->id,
                'laboratory_test_id' => $test?->id,
                'specimen_type_id' => $test?->specimen_type_id,
                'test_name_snapshot' => $service->name,
                'test_code_snapshot' => $service->code,
                'unit_price_snapshot' => $item->unit_price,
                'payer_amount' => $item->payer_amount,
                'insurance_amount' => $item->insurance_amount,
                'patient_amount' => $item->patient_amount,
                'priority' => $order->priority,
                'status' => $order->payment_status === ClinicalPaymentStatus::Pending ? 'awaiting_payment' : 'ready_for_collection',
                'invoice_item_id' => $item->id,
                'created_by' => $actor->id,
            ]);

            $this->audit($actor, 'laboratory_charge_added', $orderItem, [
                'facility_id' => $order->facility_id,
                'visit_id' => $order->visit_id,
                'laboratory_order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'invoice_item_id' => $item->id,
                'service_id' => $service->id,
                'amount' => (float) $item->patient_amount,
            ]);
        }
    }

    private function resolveInvoice(LaboratoryOrder $order, $actor): Invoice
    {
        $invoice = Invoice::query()
            ->where('facility_id', $order->facility_id)
            ->where('visit_id', $order->visit_id)
            ->lockForUpdate()
            ->oldest('id')
            ->first();

        return $invoice ?: $this->invoices->createVisitInvoice($order->visit, [], $actor);
    }

    /** @param Collection<int, Service> $services */
    private function ensureNoDuplicateActiveTests(ClinicalEncounter $encounter, Collection $services): void
    {
        $duplicate = LaboratoryOrder::query()
            ->where('clinical_encounter_id', $encounter->id)
            ->whereNotIn('status', [ClinicalOrderStatus::Cancelled->value, ClinicalOrderStatus::Completed->value])
            ->whereHas('items', fn ($query) => $query->whereIn('service_id', $services->pluck('id')))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'service_ids' => 'One or more selected laboratory tests already have an active order for this consultation.',
            ]);
        }
    }

    /** @param array<int, int|string> $serviceIds
     * @return Collection<int, Service>
     */
    private function validatedServices(ClinicalEncounter $encounter, array $serviceIds): Collection
    {
        return $this->validatedServicesForFacility($encounter->facility_id, $serviceIds);
    }

    /** @param array<int, int|string> $serviceIds
     * @return Collection<int, Service>
     */
    private function validatedServicesForFacility(int $facilityId, array $serviceIds): Collection
    {
        $ids = collect($serviceIds)->map(fn ($id): int => (int) $id)->unique()->values();
        $services = Service::query()
            ->where('facility_id', $facilityId)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get();

        if ($ids->isEmpty() || $services->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'service_ids' => 'Every selected laboratory service must be active and belong to the current facility.',
            ]);
        }

        if ($services->contains(fn (Service $service): bool => $service->service_type !== ServiceType::LaboratoryTest)) {
            throw ValidationException::withMessages([
                'service_ids' => 'Only laboratory services can be added to a laboratory order.',
            ]);
        }

        return $services;
    }

    public function resolvePaymentStatus(ClinicalEncounter $encounter): ClinicalPaymentStatus
    {
        return $this->resolveVisitPaymentStatus($encounter->visit);
    }

    public function resolveVisitPaymentStatus(Visit $visit): ClinicalPaymentStatus
    {
        return match ($visit->payer_type) {
            PayerType::Cash => ClinicalPaymentStatus::Pending,
            PayerType::Insurance, PayerType::Corporate => ClinicalPaymentStatus::Covered,
            PayerType::Exempted => ClinicalPaymentStatus::Waived,
            default => ClinicalPaymentStatus::NotRequired,
        };
    }

    public function activateLaboratoryQueue(LaboratoryOrder $order, $actor): void
    {
        $laboratory = Department::query()
            ->where('facility_id', $order->facility_id)
            ->where('code', 'LAB')
            ->where('is_active', true)
            ->where('queue_enabled', true)
            ->first();
        if (! $laboratory) {
            throw ValidationException::withMessages(['destination' => 'Laboratory queue is not configured.']);
        }

        $this->workflow->createQueue(
            $order->visit,
            $laboratory,
            $actor,
            VisitStatus::AwaitingLab,
            'Direct laboratory order ready for sample collection',
            true,
        );
    }

    public function cancelOrder(LaboratoryOrder $order, string $reason, $actor): LaboratoryOrder
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu ya kufuta order inahitajika.']);
        }
        $order->update(['status' => ClinicalOrderStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $reason, 'updated_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'lab_order_cancelled', 'subject_type' => $order::class, 'subject_id' => $order->id]);

        return $order->refresh();
    }

    private function audit($actor, string $event, $subject, array $values = []): void
    {
        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => $event,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'new_values' => $values ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
