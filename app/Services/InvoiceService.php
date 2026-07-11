<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PayerType;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientPayerProfile;
use App\Models\Service;
use App\Models\Visit;

class InvoiceService
{
    public function __construct(private readonly SequenceNumberService $numbers, private readonly ServicePricingService $pricing) {}

    public function generateInvoiceNumber(int $facilityId): string
    {
        return $this->numbers->next('invoice_number_sequences', $facilityId, 'INV', 6);
    }

    public function createVisitInvoice(Visit $visit, array $services, $actor): Invoice
    {
        $status = match ($visit->payer_type) {
            PayerType::Insurance => InvoiceStatus::CoveredByInsurance,
            PayerType::Exempted => InvoiceStatus::WrittenOff,
            default => InvoiceStatus::Pending,
        };

        $invoice = Invoice::query()->create([
            'facility_id' => $visit->facility_id,
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->id,
            'invoice_number' => $this->generateInvoiceNumber($visit->facility_id),
            'payer_type' => $visit->payer_type,
            'patient_payer_profile_id' => $visit->patient_payer_profile_id,
            'invoice_status' => $status,
            'issued_at' => now(),
            'created_by' => $actor->id,
        ]);

        foreach ($services as $service) {
            if ($service instanceof Service) {
                $this->addServiceItem($invoice, $service, $actor);
            }
        }

        return $this->calculateTotals($invoice);
    }

    public function createPatientInvoice(Patient $patient, PayerType $payerType, ?PatientPayerProfile $payerProfile, $actor, ?string $notes = null): Invoice
    {
        $status = match ($payerType) {
            PayerType::Insurance => InvoiceStatus::CoveredByInsurance,
            PayerType::Exempted => InvoiceStatus::WrittenOff,
            default => InvoiceStatus::Pending,
        };

        return Invoice::query()->create([
            'facility_id' => $patient->facility_id,
            'patient_id' => $patient->id,
            'invoice_number' => $this->generateInvoiceNumber($patient->facility_id),
            'payer_type' => $payerType,
            'patient_payer_profile_id' => $payerProfile?->id,
            'invoice_status' => $status,
            'issued_at' => now(),
            'notes' => $notes,
            'created_by' => $actor->id,
        ]);
    }

    public function addServiceItem(Invoice $invoice, Service $service, $actor)
    {
        $price = $this->pricing->resolvePriceForPatient($service, $invoice->payer_type, $invoice->patientPayerProfile?->insurance_provider_id, $invoice->patientPayerProfile?->corporate_account_id);
        $total = (float) $price;
        $payerAmounts = $this->resolvePayerAmounts($invoice->payer_type, $total);

        $item = $invoice->items()->create([
            'service_id' => $service->id,
            'item_type' => $service->service_type->value,
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => $price,
            'total_amount' => $total,
            'payer_amount' => $payerAmounts['payer_amount'],
            'insurance_amount' => $payerAmounts['insurance_amount'],
            'patient_amount' => $payerAmounts['patient_amount'],
            'status' => $invoice->payer_type === PayerType::Cash ? 'pending' : 'covered',
            'metadata' => ['service_code' => $service->code],
            'created_by' => $actor->id,
        ]);

        return $item;
    }

    public function calculateTotals(Invoice $invoice): Invoice
    {
        $subtotal = $invoice->items()->sum('total_amount');
        $invoice->update([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
            'balance_amount' => max(0, $subtotal - (float) $invoice->paid_amount),
        ]);

        return $invoice->refresh();
    }

    public function cancelItem($item): void { $item->update(['status' => 'cancelled']); $this->calculateTotals($item->invoice); }

    public function resolvePayerAmounts(PayerType $payerType, float $total): array
    {
        return match ($payerType) {
            PayerType::Insurance => ['payer_amount' => $total, 'insurance_amount' => $total, 'patient_amount' => 0],
            PayerType::Corporate => ['payer_amount' => $total, 'insurance_amount' => 0, 'patient_amount' => 0],
            PayerType::Exempted => ['payer_amount' => 0, 'insurance_amount' => 0, 'patient_amount' => 0],
            default => ['payer_amount' => $total, 'insurance_amount' => 0, 'patient_amount' => $total],
        };
    }
}
