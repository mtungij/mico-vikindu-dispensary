<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentConfirmationService
{
    public function __construct(
        private readonly BillingNumberService $numbers,
        private readonly InvoiceStatusService $statuses,
        private readonly ReceiptService $receipts,
        private readonly CashierSessionService $sessions,
        private readonly BillingAuditService $audit,
        private readonly BillingWorkflowService $workflow,
    ) {}

    public function confirmPayment(Invoice $invoice, PaymentMethod $method, float $amount, $actor, array $data = []): Payment
    {
        return DB::transaction(function () use ($invoice, $method, $amount, $actor, $data): Payment {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_unless($invoice->facility_id === currentFacility()?->id, 403);
            $this->statuses->recalculate($invoice);
            $invoice = $invoice->refresh();

            if ($amount <= 0) throw ValidationException::withMessages(['amount' => 'Kiasi cha malipo lazima kiwe zaidi ya sifuri.']);
            if ($amount > (float) $invoice->balance_amount && ! $this->setting('billing_allow_overpayment', false)) {
                throw ValidationException::withMessages(['amount' => 'Malipo hayawezi kuzidi salio la invoice.']);
            }
            if ($method->requires_reference && blank($data['transaction_reference'] ?? null)) {
                throw ValidationException::withMessages(['transaction_reference' => 'Reference ya malipo inahitajika.']);
            }

            $session = $this->sessions->getActiveSession($actor, currentFacility());

            $payment = Payment::query()->create([
                'facility_id' => $invoice->facility_id,
                'patient_id' => $invoice->patient_id,
                'visit_id' => $invoice->visit_id,
                'invoice_id' => $invoice->id,
                'cashier_session_id' => $session?->id,
                'payment_number' => $this->numbers->payment($invoice->facility_id),
                'payment_method_id' => $method->id,
                'amount' => $amount,
                'currency' => $invoice->currency,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payer_name' => $data['payer_name'] ?? null,
                'payer_phone' => $data['payer_phone'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'card_last_four' => $data['card_last_four'] ?? null,
                'payment_date' => now(),
                'status' => 'confirmed',
                'received_by' => $actor->id,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $payment->allocations()->create(['facility_id' => $invoice->facility_id, 'invoice_id' => $invoice->id, 'allocated_amount' => $amount, 'allocation_type' => 'invoice', 'allocated_by' => $actor->id, 'allocated_at' => now()]);
            $this->statuses->recalculate($invoice);
            $this->receipts->createForPayment($payment);
            $this->audit->record('payment_confirmed', $payment, [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'patient_id' => $invoice->patient_id,
                'amount' => (float) $payment->amount,
                'payment_method_id' => $method->id,
                'payment_method' => $method->name,
                'received_by' => $actor->id,
                'received_by_name' => $actor->name,
                'payment_date' => $payment->payment_date?->toISOString(),
                'facility_id' => $invoice->facility_id,
                'cashier_session_id' => $payment->cashier_session_id,
            ]);
            $this->workflow->releasePaidInvoice($invoice->refresh(), $actor);

            return $payment->refresh();
        });
    }

    protected function setting(string $key, bool $default): bool
    {
        $value = \App\Models\FacilitySetting::query()->where('facility_id', currentFacility()?->id)->where('key', $key)->value('value');
        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
