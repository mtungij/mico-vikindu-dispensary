<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Models\Invoice;

class BillingPayerSplitService
{
    public function split(PayerType|string $payerType, float $gross, float $discount = 0, float $waiver = 0): array
    {
        $payerType = $payerType instanceof PayerType ? $payerType : PayerType::from((string) $payerType);
        $net = max(0, $gross - $discount - $waiver);

        return match ($payerType) {
            PayerType::Insurance => ['patient_amount' => 0.0, 'insurance_amount' => $net, 'corporate_amount' => 0.0, 'payer_amount' => $net, 'net_amount' => $net],
            PayerType::Corporate => ['patient_amount' => 0.0, 'insurance_amount' => 0.0, 'corporate_amount' => $net, 'payer_amount' => $net, 'net_amount' => $net],
            PayerType::Exempted => ['patient_amount' => 0.0, 'insurance_amount' => 0.0, 'corporate_amount' => 0.0, 'payer_amount' => 0.0, 'net_amount' => 0.0],
            default => ['patient_amount' => $net, 'insurance_amount' => 0.0, 'corporate_amount' => 0.0, 'payer_amount' => $net, 'net_amount' => $net],
        };
    }

    public function recalculateInvoiceSplit(Invoice $invoice): Invoice
    {
        $items = $invoice->items()->whereNotIn('status', ['cancelled', 'reversed', 'non_billable'])->get();
        $patient = (float) $items->sum('patient_amount');
        $insurance = (float) $items->sum('insurance_amount');
        $corporate = (float) $items->sum('corporate_amount');
        $gross = (float) $items->sum('gross_amount');
        $discount = (float) $items->sum('discount_amount');
        $waiver = (float) $items->sum('waiver_amount');
        $tax = (float) $items->sum('tax_amount');
        $total = max(0, $patient + $insurance + $corporate + $tax);

        $invoice->update([
            'subtotal' => $gross,
            'gross_total' => $gross,
            'discount_amount' => $discount,
            'waiver_amount' => $waiver,
            'tax_amount' => $tax,
            'patient_amount' => $patient,
            'insurance_amount' => $insurance,
            'corporate_amount' => $corporate,
            'total_amount' => $total,
        ]);

        return $invoice->refresh();
    }
}
