<?php

namespace App\Services;

use App\Models\DispensingItem;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\Service;

class PharmacyPricingService
{
    public function __construct(private readonly ServicePricingService $servicePricing, private readonly InvoiceService $invoices) {}

    public function resolveUnitPrice(Medicine $medicine, Prescription $prescription): float
    {
        if ($medicine->service) {
            return (float) $this->servicePricing->resolvePriceForPatient($medicine->service, $prescription->visit->payer_type, $prescription->visit->payerProfile?->insurance_provider_id, $prescription->visit->payerProfile?->corporate_account_id);
        }
        return (float) ($medicine->default_dispensing_price ?? 0);
    }

    public function calculateDispensingAmount(Medicine $medicine, Prescription $prescription, string $quantity): array
    {
        $unit = $this->resolveUnitPrice($medicine, $prescription);
        $total = round($unit * (float) $quantity, 2);
        return ['unit_price' => $unit, 'total' => $total, ...$this->invoices->resolvePayerAmounts($prescription->visit->payer_type, $total)];
    }

    public function updateInvoiceForDispensing(DispensingItem $item, $actor): void
    {
        $prescriptionItem = $item->prescriptionItem()->with('prescription.visit.invoice')->first();
        $invoice = $prescriptionItem?->prescription?->visit?->invoice;
        $service = $item->medicine?->service;
        if (! $invoice || ! $service instanceof Service) {
            return;
        }

        $invoiceItem = $prescriptionItem->invoiceItem ?: $invoice->items()->create([
            'service_id' => $service->id,
            'item_type' => 'medicine',
            'description' => $item->medicine->name,
            'quantity' => $item->dispensed_quantity,
            'unit_price' => $item->unit_price_snapshot,
            'total_amount' => $item->total_amount,
            'payer_amount' => $item->payer_amount,
            'insurance_amount' => $item->insurance_amount,
            'patient_amount' => $item->patient_amount,
            'status' => $invoice->payer_type->value === 'cash' ? 'pending' : 'covered',
            'metadata' => ['dispensing_item_id' => $item->id],
            'created_by' => $actor->id,
        ]);

        if ($prescriptionItem->invoice_item_id) {
            $invoiceItem->update([
                'quantity' => $item->dispensed_quantity,
                'unit_price' => $item->unit_price_snapshot,
                'total_amount' => $item->total_amount,
                'payer_amount' => $item->payer_amount,
                'insurance_amount' => $item->insurance_amount,
                'patient_amount' => $item->patient_amount,
            ]);
        } else {
            $prescriptionItem->update(['invoice_item_id' => $invoiceItem->id]);
        }

        $this->invoices->calculateTotals($invoice);
    }
}
