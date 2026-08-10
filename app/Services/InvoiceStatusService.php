<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

class InvoiceStatusService
{
    public function recalculate(Invoice $invoice): Invoice
    {
        $invoice->items()->get()->each(function ($item): void {
            $allocated = (float) $item->invoice
                ->payments()
                ->where('status', 'confirmed')
                ->whereHas('allocations', fn ($query) => $query
                    ->where('invoice_item_id', $item->id)
                    ->whereNull('reversed_at'))
                ->withSum(['allocations as item_allocated' => fn ($query) => $query
                    ->where('invoice_item_id', $item->id)
                    ->whereNull('reversed_at')], 'allocated_amount')
                ->get()
                ->sum('item_allocated');
            $item->updateQuietly(['paid_amount' => min((float) $item->patient_amount, $allocated)]);
        });
        app(BillingPayerSplitService::class)->recalculateInvoiceSplit($invoice);
        $invoice = $invoice->refresh();
        $paid = (float) $invoice->payments()->where('status', 'confirmed')->sum('amount') - (float) $invoice->refunded_amount;
        $balance = max(0, (float) $invoice->patient_amount - $paid - (float) $invoice->waiver_amount);
        $paymentStatus = $balance <= 0 && (float) $invoice->patient_amount <= 0
            ? 'covered'
            : ($paid <= 0 ? 'unpaid' : ($balance > 0 ? 'partial' : ($paid > (float) $invoice->patient_amount ? 'overpaid' : 'paid')));
        $status = match (true) {
            $invoice->status === 'voided' => 'voided',
            $paymentStatus === 'covered' => InvoiceStatus::CoveredByInsurance->value,
            $paymentStatus === 'paid', $paymentStatus === 'overpaid' => 'paid',
            $paymentStatus === 'partial' => 'partially_paid',
            default => $invoice->status ?: 'open',
        };

        $invoice->update([
            'paid_amount' => max(0, $paid),
            'balance_amount' => $balance,
            'payment_status' => $paymentStatus,
            'status' => $status,
            'invoice_status' => InvoiceStatus::tryFrom($status) ?? $invoice->invoice_status,
        ]);

        return $invoice->refresh();
    }

    public function markFinalized(Invoice $invoice, $actor): Invoice
    {
        $invoice->update(['status' => 'finalized', 'finalized_at' => now(), 'finalized_by' => $actor->id]);

        return $this->recalculate($invoice);
    }
}
