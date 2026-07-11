<?php

namespace App\Services;

use App\Enums\ClinicalOrderStatus;
use App\Enums\ClinicalPaymentStatus;
use App\Enums\PayerType;
use App\Enums\ServiceType;
use App\Models\ActivityLog;
use App\Models\ClinicalEncounter;
use App\Models\LaboratoryOrder;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryOrderService
{
    public function __construct(private readonly SequenceNumberService $numbers, private readonly InvoiceService $invoices) {}
    public function generateOrderNumber(int $facilityId): string { return $this->numbers->next('laboratory_order_number_sequences', $facilityId, 'LAB', 6); }

    public function createOrder(ClinicalEncounter $encounter, array $data, $actor): LaboratoryOrder
    {
        return DB::transaction(function () use ($encounter, $data, $actor) {
            $status = $this->resolvePaymentStatus($encounter) === ClinicalPaymentStatus::Pending ? ClinicalOrderStatus::AwaitingPayment : ClinicalOrderStatus::Ordered;
            $order = LaboratoryOrder::query()->create([
                'facility_id' => $encounter->facility_id,
                'patient_id' => $encounter->patient_id,
                'visit_id' => $encounter->visit_id,
                'clinical_encounter_id' => $encounter->id,
                'ordered_by' => $actor->id,
                'order_number' => $this->generateOrderNumber($encounter->facility_id),
                'priority' => $data['priority'] ?? 'normal',
                'clinical_notes' => $data['clinical_notes'] ?? null,
                'provisional_diagnosis' => $data['provisional_diagnosis'] ?? null,
                'status' => $status,
                'ordered_at' => now(),
                'payment_status' => $this->resolvePaymentStatus($encounter),
                'created_by' => $actor->id,
            ]);
            $this->addItems($order, $data['service_ids'] ?? [], $actor);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'lab_order_created', 'subject_type' => $order::class, 'subject_id' => $order->id]);
            return $order->refresh();
        });
    }

    public function addItems(LaboratoryOrder $order, array $serviceIds, $actor): void
    {
        foreach ($serviceIds as $serviceId) {
            $service = Service::query()->where('facility_id', $order->facility_id)->findOrFail($serviceId);
            if ($service->service_type !== ServiceType::LaboratoryTest) {
                throw ValidationException::withMessages(['service_ids' => 'Huduma ya laboratory pekee ndiyo inaruhusiwa kwenye lab order.']);
            }
            $invoice = $order->visit->invoice ?: $this->invoices->createVisitInvoice($order->visit, [], $actor);
            $item = $this->invoices->addServiceItem($invoice, $service, $actor);
            $test = \App\Models\LaboratoryTest::query()->where('facility_id', $order->facility_id)->where('service_id', $service->id)->first();
            $order->items()->create([
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
                'status' => 'ordered',
                'invoice_item_id' => $item->id,
                'created_by' => $actor->id,
            ]);
        }
    }

    public function resolvePaymentStatus(ClinicalEncounter $encounter): ClinicalPaymentStatus
    {
        return match ($encounter->visit->payer_type) {
            PayerType::Cash => ClinicalPaymentStatus::Pending,
            PayerType::Insurance, PayerType::Corporate => ClinicalPaymentStatus::Covered,
            PayerType::Exempted => ClinicalPaymentStatus::Waived,
            default => ClinicalPaymentStatus::NotRequired,
        };
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
}
