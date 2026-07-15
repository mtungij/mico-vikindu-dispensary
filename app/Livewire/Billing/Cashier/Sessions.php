<?php

namespace App\Livewire\Billing\Cashier;

use App\Livewire\Forms\CashierSessionOpenForm;
use App\Models\CashierSession;
use App\Services\CashierSessionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Masmerise\Toaster\Toaster as Notifier;

class Sessions extends Component
{
    use WithPagination;

    public CashierSessionOpenForm $openForm;

    public bool $showOpen = false;

    public bool $showClose = false;

    public string $declared_cash = '0';

    public ?string $variance_reason = null;

    public ?string $notes = null;

    public string $mode = 'index';

    public ?string $statusFilter = null;

    public ?string $shiftFilter = null;

    public ?string $dateFilter = null;

    public function mount(string $mode = 'index'): void
    {
        Gate::authorize('viewAny', CashierSession::class);
        $this->mode = in_array($mode, ['index', 'current', 'history'], true) ? $mode : 'index';
    }

    public function create(): void
    {
        Gate::authorize('create', CashierSession::class);
        $this->resetErrorBag();
        $this->openForm->resetForm();
        $this->showOpen = true;
    }

    public function openSession(CashierSessionService $service): void
    {
        Gate::authorize('create', CashierSession::class);
        $this->openForm->validate();
        $data = $this->openForm->normalize();

        try {
            $session = $service->openSession(
                auth()->user(),
                $data['shift'],
                $data['opening_float'],
                $data['cash_drawer'],
                $data['notes'],
            );

            $this->showOpen = false;
            $this->openForm->resetForm();
            Notifier::success("Cashier session {$session->session_number} imefunguliwa.");
        } catch (ValidationException $exception) {
            Notifier::warning('Cashier session haikuweza kufunguliwa.');
            throw $exception;
        }
    }

    public function open(CashierSessionService $service): void
    {
        $this->openSession($service);
    }

    public function close(CashierSessionService $service): void
    {
        $session = $service->getActiveSession(auth()->user(), currentFacility());

        if (! $session) {
            $this->addError('session', 'Huna cashier session iliyo wazi.');
            return;
        }

        Gate::authorize('close', $session);

        $this->validate([
            'declared_cash' => ['required', 'numeric', 'min:0'],
            'variance_reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $expected = $service->calculateExpectedCash($session);
        if (round(((float) $this->declared_cash) - $expected, 2) != 0.0 && blank($this->variance_reason)) {
            $this->addError('variance_reason', 'Andika sababu ya tofauti ya cash.');
            return;
        }

        $service->closeSession($session, (float) $this->declared_cash, $this->variance_reason ?: $this->notes, auth()->user());
        $this->showClose = false;
        $this->declared_cash = '0';
        $this->variance_reason = null;
        $this->notes = null;
        Notifier::success('Cashier session imefungwa.');
    }

    public function closeModal(): void
    {
        $this->showOpen = false;
        $this->showClose = false;
        $this->resetErrorBag();
    }

    public function render(CashierSessionService $service)
    {
        $activeSession = $service->getActiveSession(auth()->user(), currentFacility());
        $sessions = CashierSession::query()
            ->forCurrentFacility()
            ->with('cashier')
            ->when(! auth()->user()->can('cashier.sessions.view-all'), fn ($query) => $query->where('user_id', auth()->id()))
            ->when($this->mode === 'current', fn ($query) => $query->where('user_id', auth()->id())->whereIn('status', ['open', 'pending_close', 'variance_review']))
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->shiftFilter, fn ($query) => $query->where('shift', $this->shiftFilter))
            ->when($this->dateFilter, fn ($query) => $query->whereDate('opened_at', $this->dateFilter))
            ->latest('opened_at')
            ->paginate(12);

        $totals = $activeSession ? $service->paymentTotals($activeSession) : [];
        $expected = $activeSession ? $service->calculateExpectedCash($activeSession) : 0.0;

        return view('livewire.billing.cashier.sessions', [
            'activeSession' => $activeSession,
            'sessions' => $sessions,
            'totals' => $totals,
            'expected' => $expected,
        ])->layout('components.layouts.app', [
            'title' => $this->mode === 'current' ? 'Current Cashier Session' : 'Cashier Sessions',
            'description' => 'Open, close and review cashier sessions.',
        ]);
    }
}
