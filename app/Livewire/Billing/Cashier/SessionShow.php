<?php

namespace App\Livewire\Billing\Cashier;

use App\Models\CashierSession;
use App\Services\CashierSessionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class SessionShow extends Component
{
    public CashierSession $cashierSession;

    public function mount(CashierSession $cashierSession): void
    {
        Gate::authorize('view', $cashierSession);
        $this->cashierSession = $cashierSession->load(['cashier', 'payments.method', 'payments.invoice.patient']);
    }

    public function render(CashierSessionService $sessions)
    {
        return view('livewire.billing.cashier.session-show', [
            'expected' => $sessions->calculateExpectedCash($this->cashierSession),
        ])->layout('components.layouts.app', [
            'title' => $this->cashierSession->session_number,
            'description' => 'Cashier session collections and variance.',
        ]);
    }
}
