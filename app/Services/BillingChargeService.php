<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
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
    ) {}

    public function addServiceCharge(Invoice $invoice, Service $service, $actor, ?Model $source = null, float $quantity = 1, array $metadata = []): InvoiceItem
    {
        if ($source && $this->preventDuplicateCharge($invoice, $source)) {
            return InvoiceItem::query()->where('invoice_id', $invoice->id)->where('reference_type', $source::class)->where('reference_id', $source->getKey())->firstOrFail();
        }

        $price = $this->pricing->getCurrentPrice($service, $invoice->payer_type, $invoice->insurance_provider_id ?? $invoice->patientPayerProfile?->insurance_provider_id, $invoice->corporate_account_id ?? $invoice->patientPayerProfile?->corporate_account_id);
        if (! $price && $service->requires_payment) {
            throw ValidationException::withMessages(['service_id' => "Huduma ya {$service->name} bado haijawekewa bei ya ".$invoice->payer_type->label().'.']);
        }

        $unit = (float) ($price?->amount ?? 0);
        $gross = round($unit * $quantity, 2);
        $split = $this->split->split($invoice->payer_type, $gross);

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
            'price_snapshot' => $price?->only(['id','amount','currency','payer_type','effective_from','effective_to']),
            'metadata' => $metadata,
            'created_by' => $actor->id,
        ]);

        $this->statuses->recalculate($invoice);
        $this->audit->record('invoice_item_added', $item);

        return $item;
    }

    public function preventDuplicateCharge(Invoice $invoice, Model $source): bool
    {
        return InvoiceItem::query()->where('invoice_id', $invoice->id)->where('reference_type', $source::class)->where('reference_id', $source->getKey())->whereNotIn('status', ['cancelled', 'reversed'])->exists();
    }

    public function cancelCharge(InvoiceItem $item, $actor, string $reason): void
    {
        $item->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason, 'updated_by' => $actor->id]);
        $this->statuses->recalculate($item->invoice);
        $this->audit->record('invoice_item_cancelled', $item, ['reason' => $reason]);
    }
}
