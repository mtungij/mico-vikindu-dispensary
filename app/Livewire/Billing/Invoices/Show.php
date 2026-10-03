<?php

namespace App\Livewire\Billing\Invoices;

use App\Livewire\Forms\CashierSessionOpenForm;
use App\Models\CashierSession;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Services\CashierSessionService;
use App\Services\InvoiceStatusService;
use App\Services\PaymentConfirmationService;
use App\Services\PrescriptionBillingService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    public array $selectedItems = [];

    public array $itemAmounts = [];

    public array $medicineQuantities = [];

    public ?string $transaction_reference = null;

    public ?string $payment_idempotency_key = null;

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

    public function openPaymentModal(): void
    {
        Gate::authorize('create', Payment::class);

        $this->resetErrorBag();
        $this->invoice = $this->loadInvoice($this->invoice->refresh());
        $this->amount = (string) $this->invoice->balance_amount;
        $this->selectedItems = [];
        $this->itemAmounts = [];
        $this->medicineQuantities = [];
        foreach ($this->invoice->items as $item) {
            $balance = max(0, (float) $item->patient_amount - (float) $item->paid_amount);
            if ($balance > 0 && ! in_array($item->status, ['cancelled', 'reversed', 'non_billable'], true)) {
                $this->selectedItems[] = (string) $item->id;
                $this->itemAmounts[$item->id] = number_format($balance, 2, '.', '');
            }
        }
        $this->payment_idempotency_key = (string) Str::uuid();
        $this->showPaymentModal = true;
    }

    public function selectedTotal(): float
    {
        return round(collect($this->selectedItems)->unique()->sum(fn ($id) => max(0, (float) ($this->itemAmounts[$id] ?? 0))), 2);
    }

    public function updatedSelectedItems(): void
    {
        $this->amount = number_format($this->selectedTotal(), 2, '.', '');
    }

    public function updatedItemAmounts(): void
    {
        $this->medicineQuantities = [];
        $this->updatedSelectedItems();
    }

    public function updatedMedicineQuantities($value, $id): void
    {
        $item = $this->invoice->items()->with('prescriptionItem.medicine.dispensingUnit')->find($id);
        if (! $item?->prescriptionItem || ! is_numeric($value) || (float) $value <= 0) {
            $this->addError('allocations', 'Enter a positive medicine quantity.');

            return;
        }
        $quantity = (float) $value;
        $prescribed = (float) $item->prescriptionItem->quantity;
        $decimalAllowed = (bool) $item->prescriptionItem->medicine?->dispensingUnit?->decimal_allowed;
        if ((! $decimalAllowed && floor($quantity) !== $quantity) || $quantity > $prescribed || $prescribed <= 0) {
            $this->addError('allocations', 'Enter a valid quantity for this medicine.');

            return;
        }
        // Buy additional financial quantity without changing the prescription or charge.
        $due = (float) $item->patient_amount;
        $balance = max(0, $due - (float) $item->paid_amount);
        $pay = ceil(round($due * $quantity / $prescribed, 8) * 100) / 100;
        if ($pay > $balance) {
            $this->addError('allocations', 'Quantity exceeds the remaining unpaid quantity.');

            return;
        }
        $this->resetErrorBag('allocations');
        $this->itemAmounts[$id] = number_format($pay, 2, '.', '');
        $this->updatedSelectedItems();
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

    public function confirmPayment(PaymentConfirmationService $service, PrescriptionBillingService $prescriptionBilling): void
    {
        Gate::authorize('create', Payment::class);

        $data = $this->validate([
            'payment_method_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'payment_idempotency_key' => ['required', 'uuid'],
            'selectedItems' => ['required', 'array', 'min:1'],
            'selectedItems.*' => ['required', 'integer', 'distinct'],
            'itemAmounts' => ['required', 'array'],
        ]);

        try {
            $invoice = Invoice::query()->forCurrentFacility()->findOrFail($this->invoice->id);
            // Payment service validates under locks and handles retries before paid-invoice rejection.

            $method = PaymentMethod::query()
                ->forCurrentFacility()
                ->where('is_active', true)
                ->findOrFail($data['payment_method_id']);

            $data['allocations'] = collect($this->selectedItems)->unique()->mapWithKeys(fn ($id) => [$id => $this->itemAmounts[$id] ?? 0])->all();
            $data['idempotency_key'] = $data['payment_idempotency_key'];
            $payment = $service->confirmPayment($invoice, $method, (float) $data['amount'], auth()->user(), $data);

            $this->payment_method_id = null;
            $this->transaction_reference = null;
            $this->payment_idempotency_key = null;
            $this->invoice = $this->loadInvoice($payment->invoice()->firstOrFail());
            $this->amount = (string) $this->invoice->balance_amount;
            $this->showPaymentModal = false;

            $payment->loadMissing('receipt');
            $receiptNumber = $payment->receipt?->receipt_number;
            $medicineReady = Prescription::query()
                ->where('visit_id', $this->invoice->visit_id)
                ->whereIn('status', ['prescribed', 'partially_dispensed'])
                ->get()
                ->contains(fn ($prescription) => $prescriptionBilling->hasFinanciallyDispensableItems($prescription));
            $message = $medicineReady
                ? 'Payment confirmed. Patient is ready for Pharmacy.'
                : ($this->invoice->payment_status === 'partial'
                    ? 'Partial payment recorded. Only the selected items received payment.'
                    : 'Malipo yamethibitishwa'.($receiptNumber ? " na risiti namba {$receiptNumber} imetengenezwa." : '.'));

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

    public function receivePayment(PaymentConfirmationService $service, PrescriptionBillingService $prescriptionBilling): void
    {
        $this->confirmPayment($service, $prescriptionBilling);
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
        return $invoice->load(['patient', 'visit', 'items.service', 'items.prescriptionItem.medicine.dispensingUnit', 'payments.method', 'receipts', 'handoffs.destinationDepartment']);
    }

    private function ensureInvoiceCanReceivePayment(Invoice $invoice): void
    {
        app(InvoiceStatusService::class)->ensureCanReceivePayment($invoice);
    }
}
