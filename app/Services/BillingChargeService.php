<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentRefund;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class BillingChargeService
{
    public function __construct(
        private readonly ServicePricingService $pricing,
        private readonly BillingPayerSplitService $split,
        private readonly InvoiceStatusService $statuses,
        private readonly BillingAuditService $audit,
        private readonly BillingNumberService $numbers,
    ) {}

    public function addServiceCharge(Invoice $invoice, Service $service, $actor, ?Model $source = null, float $quantity = 1, array $metadata = [], ?array $payerSplit = null, array $attributes = []): InvoiceItem
    {
        if ($source && $this->preventDuplicateCharge($invoice, $source, $service)) {
            return InvoiceItem::query()
                ->where('invoice_id', $invoice->id)
                ->where('reference_type', $source::class)
                ->where('reference_id', $source->getKey())
                ->where('service_id', $service->id)
                ->firstOrFail();
        }

        $price = $this->pricing->getCurrentPrice($service, $invoice->payer_type, $invoice->insurance_provider_id ?? $invoice->patientPayerProfile?->insurance_provider_id, $invoice->corporate_account_id ?? $invoice->patientPayerProfile?->corporate_account_id);
        if (! $price && $service->requires_payment) {
            throw ValidationException::withMessages(['service_id' => "Huduma ya {$service->name} bado haijawekewa bei ya ".$invoice->payer_type->label().'.']);
        }

        $unit = (float) ($price?->amount ?? 0);
        $gross = round($unit * $quantity, 2);
        $split = $payerSplit ?? $this->split->split($invoice->payer_type, $gross);

        $item = $invoice->items()->create([
            'facility_id' => $invoice->facility_id,
            'patient_id' => $invoice->patient_id,
            'visit_id' => $invoice->visit_id,
            'service_id' => $service->id,
            'item_type' => $service->service_type?->value ?? 'service',
            'reference_type' => $source ? $source::class : null,
            'reference_id' => $source?->getKey(),
            'code_snapshot' => $service->code,
            'description' => $service->name,
            'description_snapshot' => $service->name,
            'department_id' => $service->department_id,
            'quantity' => $quantity,
            'unit_price' => $unit,
            'gross_amount' => $gross,
            'total_amount' => $gross,
            'payer_amount' => $split['payer_amount'],
            'patient_amount' => $split['patient_amount'],
            'insurance_amount' => $split['insurance_amount'],
            'corporate_amount' => $split['corporate_amount'],
            'net_amount' => $split['net_amount'],
            'status' => 'pending',
            'service_date' => today(),
            'price_snapshot' => $price?->only(['id', 'amount', 'currency', 'payer_type', 'effective_from', 'effective_to']),
            'metadata' => $metadata,
            ...$attributes,
            'created_by' => $actor->id,
        ]);

        $this->statuses->recalculate($invoice);
        $this->audit->record('invoice_item_added', $item);

        return $item;
    }

    public function preventDuplicateCharge(Invoice $invoice, Model $source, ?Service $service = null): bool
    {
        return InvoiceItem::query()
            ->where('invoice_id', $invoice->id)
            ->where('reference_type', $source::class)
            ->where('reference_id', $source->getKey())
            ->when($service, fn ($query) => $query->where('service_id', $service->id))
            ->whereNotIn('status', ['cancelled', 'reversed'])
            ->exists();
    }

    public function cancelCharge(InvoiceItem $item, $actor, string $reason): void
    {
        $this->requestRefundForReduction($item, 0, $actor, $reason);
        $item->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason, 'updated_by' => $actor->id]);
        $this->statuses->recalculate($item->invoice);
        $this->audit->record('invoice_item_cancelled', $item, ['reason' => $reason]);
    }

    public function adjustChargeQuantity(InvoiceItem $item, float $quantity, $actor, string $reason): InvoiceItem
    {
        $quantity = max(0, $quantity);
        $gross = round((float) $item->unit_price * $quantity, 2);
        $oldGross = max(0.01, (float) $item->gross_amount);
        $ratio = min(1, $gross / $oldGross);
        $patient = round((float) $item->patient_amount * $ratio, 2);
        $insurance = round((float) $item->insurance_amount * $ratio, 2);
        $corporate = round((float) $item->corporate_amount * $ratio, 2);
        $this->requestRefundForReduction($item, $patient, $actor, $reason);
        $item->update([
            'quantity' => $quantity,
            'gross_amount' => $gross,
            'total_amount' => $gross,
            'patient_amount' => $patient,
            'insurance_amount' => $insurance,
            'corporate_amount' => $corporate,
            'payer_amount' => $insurance + $corporate,
            'net_amount' => $gross,
            'metadata' => [...($item->metadata ?? []), 'billing_adjustment_reason' => $reason],
            'updated_by' => $actor->id,
        ]);
        $this->statuses->recalculate($item->invoice);
        $this->audit->record('medicine_billing_adjusted', $item, ['quantity' => $quantity, 'reason' => $reason]);

        return $item->refresh();
    }

    private function requestRefundForReduction(InvoiceItem $item, float $newPatientAmount, $actor, string $reason): void
    {
        $refundAmount = max(0, (float) $item->paid_amount - $newPatientAmount);
        if ($refundAmount <= 0.005 || PaymentRefund::query()
            ->where('invoice_id', $item->invoice_id)
            ->where('status', 'pending')
            ->where('notes', 'like', '%invoice_item_id='.$item->id.'%')
            ->exists()) {
            return;
        }
        $payment = $item->invoice->payments()
            ->where('status', 'confirmed')
            ->whereHas('allocations', fn ($query) => $query->where('invoice_item_id', $item->id))
            ->latest()
            ->first();
        if (! $payment) {
            return;
        }
        PaymentRefund::query()->create([
            'facility_id' => $item->facility_id,
            'patient_id' => $item->patient_id,
            'invoice_id' => $item->invoice_id,
            'payment_id' => $payment->id,
            'refund_number' => $this->numbers->refund($item->facility_id),
            'amount' => $refundAmount,
            'refund_method_id' => $payment->payment_method_id,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => $actor->id,
            'cashier_session_id' => $payment->cashier_session_id,
            'notes' => 'Automatic medicine adjustment; invoice_item_id='.$item->id,
        ]);
        $this->audit->record('medicine_refund_requested', $item, ['amount' => $refundAmount, 'reason' => $reason]);
    }
}
