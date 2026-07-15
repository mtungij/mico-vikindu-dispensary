<?php

namespace App\Livewire\Billing\Invoices;

use App\Livewire\Forms\CashierSessionOpenForm;
use App\Models\CashierSession;
use App\Models\FacilitySetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\BillingAuditService;
use App\Services\CashierSessionService;
use App\Services\PaymentConfirmationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Masmerise\Toaster\Toaster as Notifier;
use Throwable;

class Show extends Component
{
    public Invoice $invoice;

    public bool $showPaymentModal = false;

    public ?int $payment_method_id = null;

    public string $amount = '0';

    public ?string $transaction_reference = null;

    public bool $showOpenSessionPrompt = false;

    public bool $showCashierSessionModal = false;

    public bool $returnToPaymentAfterSessionOpen = false;

    public CashierSessionOpenForm $cashierSessionForm;

    public function mount(Invoice $invoice): void
    {
        Gate::authorize('view', $invoice);

        abort_unless($invoice->facility_id === currentFacility()?->id, 404);

        $this->invoice = $this->loadInvoice($invoice);
        $this->amount = (string) $this->invoice->balance_amount;
    }

    public function openPaymentModal(CashierSessionService $sessions, BillingAuditService $audit): void
    {
        Gate::authorize('create', Payment::class);

        $this->resetErrorBag();
        $this->invoice = $this->loadInvoice($this->invoice->refresh());
        $this->amount = (string) $this->invoice->balance_amount;

        if ($this->requiresCashierSession() && ! $sessions->getActiveSession(auth()->user(), currentFacility())) {
            $this->showOpenSessionPrompt = true;
            $audit->record('cashier_session_prompt_shown', $this->invoice, [
                'invoice_id' => $this->invoice->id,
                'cashier_user_id' => auth()->id(),
                'facility_id' => currentFacility()?->id,
                'timestamp' => now()->toISOString(),
            ]);

            return;
        }

        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->resetErrorBag();
    }

    public function cancelOpenSessionPrompt(): void
    {
        $this->showOpenSessionPrompt = false;
        $this->returnToPaymentAfterSessionOpen = false;
    }

    public function openCashierSessionFromPaymentPrompt(): void
    {
        Gate::authorize('create', CashierSession::class);

        $this->showOpenSessionPrompt = false;
        $this->showCashierSessionModal = true;
        $this->returnToPaymentAfterSessionOpen = true;
        $this->cashierSessionForm->resetForm();
        $this->resetErrorBag();
    }

    public function closeCashierSessionModal(): void
    {
        $this->showCashierSessionModal = false;
        $this->returnToPaymentAfterSessionOpen = false;
        $this->resetErrorBag();
    }

    public function openCashierSession(CashierSessionService $sessions): void
    {
        Gate::authorize('create', CashierSession::class);

        $this->cashierSessionForm->validate();
        $data = $this->cashierSessionForm->normalize();

        try {
            $session = $sessions->openSession(
                auth()->user(),
                $data['shift'],
                $data['opening_float'],
                $data['cash_drawer'],
                $data['notes'],
            );

            $this->showCashierSessionModal = false;
            $this->cashierSessionForm->resetForm();
            Notifier::success("Cashier session {$session->session_number} imefunguliwa.");

            if ($this->returnToPaymentAfterSessionOpen) {
                $this->returnToPaymentAfterSessionOpen = false;
                $this->showPaymentModal = true;
                Notifier::success('Cashier session imefunguliwa. Unaweza sasa kupokea malipo.');
            }
        } catch (ValidationException $exception) {
            Notifier::warning('Cashier session haikuweza kufunguliwa.');
            throw $exception;
        }
    }

    public function updatedPaymentMethodId(): void
    {
        $this->resetErrorBag('payment_method_id');
        $this->resetErrorBag('transaction_reference');
    }

    public function confirmPayment(PaymentConfirmationService $service, CashierSessionService $sessions): void
    {
        Gate::authorize('create', Payment::class);

        $data = $this->validate([
            'payment_method_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $invoice = Invoice::query()->forCurrentFacility()->findOrFail($this->invoice->id);
            $this->ensureInvoiceCanReceivePayment($invoice);

            $method = PaymentMethod::query()
                ->forCurrentFacility()
                ->where('is_active', true)
                ->findOrFail($data['payment_method_id']);

            if ($sessions->requiresSessionForPayment((bool) $method->is_cash) && ! $sessions->getActiveSession(auth()->user(), currentFacility())) {
                $this->showPaymentModal = false;
                $this->showOpenSessionPrompt = true;
                $this->returnToPaymentAfterSessionOpen = true;
                $this->addError('cashier_session', 'Fungua cashier session kabla ya kupokea malipo.');
                Notifier::warning('Fungua cashier session kabla ya kupokea malipo.');
                return;
            }

            $payment = $service->confirmPayment($invoice, $method, (float) $data['amount'], auth()->user(), $data);

            $this->payment_method_id = null;
            $this->transaction_reference = null;
            $this->invoice = $this->loadInvoice($payment->invoice()->firstOrFail());
            $this->amount = (string) $this->invoice->balance_amount;
            $this->showPaymentModal = false;

            $payment->loadMissing('receipt');
            $receiptNumber = $payment->receipt?->receipt_number;
            $message = $this->invoice->payment_status === 'partial'
                ? 'Malipo ya sehemu yamepokelewa. Salio ni TSh '.number_format((float) $this->invoice->balance_amount, 2).'.'
                : 'Malipo yamethibitishwa'.($receiptNumber ? " na risiti namba {$receiptNumber} imetengenezwa." : '.');

            Notifier::success($message);
            $this->dispatch('payment-confirmed', invoiceId: $this->invoice->id);
        } catch (ValidationException $exception) {
            Notifier::warning('Rekebisha taarifa za malipo zilizoainishwa.');
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Payment confirmation failed from invoice screen.', [
                'invoice_id' => $this->invoice->id,
                'user_id' => auth()->id(),
                'facility_id' => currentFacility()?->id,
                'payment_method_id' => $this->payment_method_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $this->addError('payment', 'Malipo hayakuweza kuthibitishwa. Tafadhali jaribu tena au wasiliana na msimamizi.');
            Notifier::error('Malipo hayakuweza kuthibitishwa.');
        }
    }

    public function receivePayment(PaymentConfirmationService $service, CashierSessionService $sessions): void
    {
        $this->confirmPayment($service, $sessions);
    }

    public function render()
    {
        return view('livewire.billing.invoices.show', [
            'methods' => PaymentMethod::query()
                ->forCurrentFacility()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        ])->layout('components.layouts.app', [
            'title' => $this->invoice->invoice_number,
            'description' => 'Invoice details, payer split and payments.',
        ]);
    }

    private function loadInvoice(Invoice $invoice): Invoice
    {
        return $invoice->load(['patient', 'visit', 'items.service', 'payments.method', 'receipts', 'handoffs.destinationDepartment']);
    }

    private function ensureInvoiceCanReceivePayment(Invoice $invoice): void
    {
        if (! in_array($invoice->status, ['open', 'finalized', 'partially_paid'], true)) {
            throw ValidationException::withMessages(['payment' => 'Invoice hii haipo kwenye hali inayoruhusu kupokea malipo.']);
        }

        if ((float) $invoice->patient_amount <= 0 || (float) $invoice->balance_amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Invoice hii haina salio la kulipwa.']);
        }
    }

    private function requiresCashierSession(): bool
    {
        $value = FacilitySetting::query()
            ->where('facility_id', currentFacility()?->id)
            ->where('key', 'billing_require_cashier_session')
            ->value('value');

        return $value === null ? false : filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
