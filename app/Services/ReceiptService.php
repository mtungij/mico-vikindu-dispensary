<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Receipt;

class ReceiptService
{
    public function __construct(private readonly BillingNumberService $numbers, private readonly BillingAuditService $audit) {}

    public function createForPayment(Payment $payment): Receipt
    {
        $payment->loadMissing(['invoice', 'method']);
        $receipt = Receipt::query()->create([
            'facility_id' => $payment->facility_id,
            'patient_id' => $payment->patient_id,
            'visit_id' => $payment->visit_id,
            'invoice_id' => $payment->invoice_id,
            'payment_id' => $payment->id,
            'receipt_number' => $this->numbers->receipt($payment->facility_id),
            'receipt_date' => now(),
            'amount' => $payment->amount,
            'payment_method_snapshot' => $payment->method?->name ?? 'Payment',
            'transaction_reference_snapshot' => $payment->transaction_reference,
            'cashier_name_snapshot' => $payment->receivedBy?->name ?? auth()->user()?->name ?? 'Cashier',
            'status' => 'issued',
            'created_by' => $payment->received_by,
        ]);
        $this->audit->record('receipt_issued', $receipt);

        return $receipt;
    }

    public function reprint(Receipt $receipt, $actor): Receipt
    {
        $receipt->increment('reprint_count');
        $this->audit->record('receipt_reprinted', $receipt, ['by' => $actor->id]);

        return $receipt->refresh();
    }
}
