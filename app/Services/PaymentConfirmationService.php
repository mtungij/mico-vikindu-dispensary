<?php

namespace App\Services;

use App\Events\LaboratoryPaymentConfirmed;
use App\Models\FacilitySetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PrescriptionItem;
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
            abort_unless($invoice->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);
            abort_unless(
                $actor->can('billing.receive-payment') && $actor->can('billing.confirm-payment'),
                403
            );
            $this->statuses->recalculate($invoice);
            $invoice = $invoice->refresh();

            $existing = $this->existingRetry($invoice, $method, $amount, $data);
            if ($existing) {
                return $existing;
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Kiasi cha malipo lazima kiwe zaidi ya sifuri.']);
            }
            if ((float) $invoice->balance_amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Invoice hii tayari imelipwa kikamilifu.']);
            }
            if ($amount < (float) $invoice->balance_amount && ! $actor->can('billing.receive-partial-payment')) {
                throw ValidationException::withMessages(['amount' => 'Huna ruhusa ya kupokea malipo ya sehemu.']);
            }
            if ($amount > (float) $invoice->balance_amount && ! $this->setting('billing_allow_overpayment', false)) {
                throw ValidationException::withMessages(['amount' => 'Malipo hayawezi kuzidi salio la invoice.']);
            }
            if (! $method->is_active || ($method->facility_id !== null && $method->facility_id !== $invoice->facility_id)) {
                throw ValidationException::withMessages(['payment_method_id' => 'Njia ya malipo haipatikani kwa facility hii.']);
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
                'idempotency_key' => $data['idempotency_key'] ?? null,
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

            $this->allocateToItems($payment, $invoice, $amount, $actor);
            $invoice = $this->statuses->recalculate($invoice);
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
            app(PrescriptionBillingService::class)->releasePaidInvoice($invoice->refresh(), $actor);
            if ((float) $invoice->balance_amount === 0.0 && $invoice->payment_status === 'paid') {
                LaboratoryPaymentConfirmed::dispatch($invoice->refresh(), $actor);
                app(ProcedureOrderService::class)->releasePaidInvoice($invoice->refresh(), $actor);
            }

            return $payment->refresh();
        });
    }

    protected function setting(string $key, bool $default): bool
    {
        $value = FacilitySetting::query()->where('facility_id', currentFacility()?->id)->where('key', $key)->value('value');

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function existingRetry(Invoice $invoice, PaymentMethod $method, float $amount, array $data): ?Payment
    {
        $query = Payment::query()->where('facility_id', $invoice->facility_id)->where('status', 'confirmed');
        if (filled($data['idempotency_key'] ?? null)) {
            $existing = (clone $query)->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
        } elseif (filled($data['transaction_reference'] ?? null)) {
            $existing = (clone $query)
                ->where('payment_method_id', $method->id)
                ->where('transaction_reference', $data['transaction_reference'])
                ->lockForUpdate()
                ->first();
        } else {
            return null;
        }
        if (! $existing) {
            return null;
        }
        if ($existing->invoice_id !== $invoice->id || abs((float) $existing->amount - $amount) > 0.005) {
            throw ValidationException::withMessages(['payment' => 'This payment reference was already used for a different payment.']);
        }

        return $existing;
    }

    private function allocateToItems(Payment $payment, Invoice $invoice, float $amount, $actor): void
    {
        $remaining = $amount;
        $items = $invoice->items()
            ->whereNotIn('status', ['cancelled', 'reversed', 'non_billable'])
            ->orderByRaw('case when reference_type = ? then 0 else 1 end', [PrescriptionItem::class])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            if ($remaining <= 0.005) {
                break;
            }
            $outstanding = max(0, (float) $item->patient_amount - (float) $item->paid_amount);
            $allocated = min($remaining, $outstanding);
            if ($allocated <= 0) {
                continue;
            }
            $payment->allocations()->create([
                'facility_id' => $invoice->facility_id,
                'invoice_id' => $invoice->id,
                'invoice_item_id' => $item->id,
                'allocated_amount' => $allocated,
                'allocation_type' => 'invoice_item',
                'allocated_by' => $actor->id,
                'allocated_at' => now(),
            ]);
            $item->update(['paid_amount' => (float) $item->paid_amount + $allocated]);
            $remaining -= $allocated;
        }

        if ($remaining > 0.005) {
            $payment->allocations()->create([
                'facility_id' => $invoice->facility_id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $remaining,
                'allocation_type' => 'invoice',
                'allocated_by' => $actor->id,
                'allocated_at' => now(),
            ]);
        }
    }
}
