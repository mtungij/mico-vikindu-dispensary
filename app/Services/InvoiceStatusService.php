<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

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
        $terminalStatuses = ['cancelled', 'void', 'voided', 'refunded', 'written_off', 'reversed', 'replaced'];
        $status = match (true) {
            in_array($invoice->status, $terminalStatuses, true) => $invoice->status,
            $paymentStatus === 'covered' => InvoiceStatus::CoveredByInsurance->value,
            $paymentStatus === 'paid', $paymentStatus === 'overpaid' => 'paid',
            $paymentStatus === 'partial' => 'partially_paid',
            $invoice->status === 'finalized' => 'finalized',
            $invoice->status === InvoiceStatus::Draft->value => InvoiceStatus::Draft->value,
            default => 'open',
        };

        $invoice->update([
            'paid_amount' => max(0, $paid),
            'balance_amount' => $balance,
            'payment_status' => $paymentStatus,
            'status' => $status,
            'invoice_status' => $this->invoiceStatusFor($status),
        ]);

        return $invoice->refresh();
    }

    public function markFinalized(Invoice $invoice, $actor): Invoice
    {
        $invoice->update(['status' => 'finalized', 'finalized_at' => now(), 'finalized_by' => $actor->id]);

        return $this->recalculate($invoice);
    }

    public function ensureCanReceivePayment(Invoice $invoice, bool $recalculate = true): Invoice
    {
        $invoice = $recalculate ? $this->recalculate($invoice) : $invoice->refresh();
        $invoiceStatus = $invoice->invoice_status?->value ?? (string) $invoice->invoice_status;

        if ($invoice->voided_at || in_array($invoice->status, ['void', 'voided'], true)) {
            throw ValidationException::withMessages(['payment' => 'Invoice hii imebatilishwa.']);
        }
        if ($invoice->status === 'cancelled' || $invoiceStatus === InvoiceStatus::Cancelled->value) {
            throw ValidationException::withMessages(['payment' => 'Invoice hii imefutwa.']);
        }
        if ($invoice->status === 'refunded' || $invoiceStatus === InvoiceStatus::Refunded->value) {
            throw ValidationException::withMessages(['payment' => 'Invoice hii imerejeshewa malipo.']);
        }
        if ($invoice->status === 'written_off' || $invoiceStatus === InvoiceStatus::WrittenOff->value) {
            throw ValidationException::withMessages(['payment' => 'Invoice hii imeondolewa kwenye madai.']);
        }
        if (in_array($invoice->status, ['reversed', 'replaced'], true)) {
            throw ValidationException::withMessages(['payment' => 'Invoice hii imebadilishwa na haiwezi kupokea malipo.']);
        }
        if ($invoice->status === InvoiceStatus::Draft->value || $invoiceStatus === InvoiceStatus::Draft->value) {
            throw ValidationException::withMessages(['payment' => 'Invoice hii bado haijafinalize.']);
        }
        if (in_array($invoice->payment_status, ['paid', 'overpaid'], true) || $invoice->status === 'paid') {
            throw ValidationException::withMessages(['amount' => 'Invoice hii tayari imelipwa kikamilifu.']);
        }
        if ((float) $invoice->patient_amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Invoice hii haina sehemu ya malipo ya mgonjwa.']);
        }
        if ((float) $invoice->balance_amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Invoice hii haina salio la kulipwa.']);
        }
        if (! in_array($invoice->status, ['open', 'finalized', 'partially_paid'], true)) {
            throw ValidationException::withMessages(['payment' => 'Hali ya invoice hii hairuhusu kupokea malipo.']);
        }

        return $invoice;
    }

    private function invoiceStatusFor(string $status): InvoiceStatus
    {
        return match ($status) {
            'open', 'finalized' => InvoiceStatus::Pending,
            'void', 'voided' => InvoiceStatus::Cancelled,
            default => InvoiceStatus::tryFrom($status) ?? InvoiceStatus::Pending,
        };
    }
}
