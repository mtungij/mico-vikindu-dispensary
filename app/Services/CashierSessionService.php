<?php

namespace App\Services;

use App\Models\CashierSession;
use App\Models\Facility;
use App\Models\FacilitySetting;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CashierSessionService
{
    private const ACTIVE_STATUSES = ['open', 'pending_close', 'variance_review'];

    public function __construct(private readonly BillingNumberService $numbers, private readonly BillingAuditService $audit) {}

    public function getActiveSession(?User $cashier = null, ?Facility $facility = null): ?CashierSession
    {
        $cashier ??= auth()->user();
        $facility ??= currentFacility();

        if (! $cashier || ! $facility) {
            return null;
        }

        return CashierSession::query()
            ->where('facility_id', $facility->id)
            ->where('user_id', $cashier->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest('opened_at')
            ->first();
    }

    public function requiresSessionForPayment(bool $isCash): bool
    {
        return $this->setting('billing_require_cashier_session', false)
            && ($isCash || $this->setting('billing_require_session_for_non_cash', false));
    }

    public function openSession(
        User $cashier,
        string|float|int $shift = 'morning',
        string|float|int|null $openingFloat = '0',
        ?string $cashDrawer = null,
        ?string $notes = null,
    ): CashierSession {
        Gate::authorize('create', CashierSession::class);

        if (is_numeric($shift)) {
            $notes = is_string($openingFloat) && ! is_numeric($openingFloat) ? $openingFloat : $notes;
            $openingFloat = $shift;
            $shift = 'morning';
        }

        $facility = currentFacility();
        abort_unless($facility, 403);

        if (! $cashier->isActive()) {
            throw ValidationException::withMessages(['session' => 'Mtumiaji huyu hana akaunti hai.']);
        }

        if (! $cashier->belongsToCurrentFacility()) {
            throw ValidationException::withMessages(['session' => 'Cashier huyu si wa kituo hiki.']);
        }

        if ($existing = $this->getActiveSession($cashier, $facility)) {
            $this->audit->record('cashier_session_open_attempt_blocked', $existing, [
                'session_number' => $existing->session_number,
                'cashier_user_id' => $cashier->id,
                'facility_id' => $facility->id,
                'timestamp' => now()->toISOString(),
            ]);

            throw ValidationException::withMessages([
                'session' => "Una cashier session ambayo bado iko wazi: {$existing->session_number}.",
            ]);
        }

        $amount = $this->normalizeOpeningFloat($openingFloat);
        $shift = $this->normalizeShift((string) $shift);

        return DB::transaction(function () use ($cashier, $facility, $shift, $amount, $cashDrawer, $notes): CashierSession {
            $existing = CashierSession::query()
                ->where('facility_id', $facility->id)
                ->where('user_id', $cashier->id)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->latest('opened_at')
                ->first();

            if ($existing) {
                $this->audit->record('cashier_session_open_attempt_blocked', $existing, [
                    'session_number' => $existing->session_number,
                    'cashier_user_id' => $cashier->id,
                    'facility_id' => $facility->id,
                    'timestamp' => now()->toISOString(),
                ]);

                throw ValidationException::withMessages([
                    'session' => "Una cashier session ambayo bado iko wazi: {$existing->session_number}.",
                ]);
            }

            $session = CashierSession::query()->create([
                'facility_id' => $facility->id,
                'user_id' => $cashier->id,
                'session_number' => $this->numbers->cashierSession($facility->id),
                'shift' => $shift,
                'opened_at' => now(),
                'opening_float' => $amount,
                'cash_drawer' => blank($cashDrawer) ? null : trim((string) $cashDrawer),
                'status' => 'open',
                'notes' => $notes,
                'opened_by' => auth()->id() ?? $cashier->id,
            ]);

            $this->audit->record('cashier_session_opened', $session, [
                'session_id' => $session->id,
                'session_number' => $session->session_number,
                'cashier_user_id' => $cashier->id,
                'facility_id' => $facility->id,
                'opening_float' => $amount,
                'shift' => $shift,
                'timestamp' => now()->toISOString(),
            ]);

            return $session;
        });
    }

    public function calculateExpectedCash(CashierSession $session): float
    {
        $cashMethods = PaymentMethod::query()
            ->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $session->facility_id))
            ->where('is_cash', true)
            ->pluck('id');

        $cash = $session->payments()
            ->whereIn('payment_method_id', $cashMethods)
            ->where('status', 'confirmed')
            ->sum('amount');

        return round((float) $session->opening_float + (float) $cash, 2);
    }

    public function paymentTotals(CashierSession $session): array
    {
        $session->loadMissing('payments.method', 'payments.receipt');

        $totals = [
            'cash' => 0.0,
            'mobile_money' => 0.0,
            'card' => 0.0,
            'bank' => 0.0,
            'bank_transfer' => 0.0,
            'cheque' => 0.0,
            'non_cash' => 0.0,
            'payments_count' => $session->payments->count(),
            'receipts_count' => $session->payments->filter(fn ($payment) => $payment->receipt !== null)->count(),
        ];

        foreach ($session->payments->where('status', 'confirmed') as $payment) {
            $amount = (float) $payment->amount;
            $type = $payment->method?->type;

            if ($payment->method?->is_cash) {
                $totals['cash'] += $amount;
                continue;
            }

            $totals['non_cash'] += $amount;
            if (array_key_exists((string) $type, $totals)) {
                $totals[(string) $type] += $amount;
            }
        }

        return $totals;
    }

    public function closeSession(CashierSession $session, float $declaredCash, ?string $notes, User $actor): CashierSession
    {
        Gate::authorize('close', $session);

        return DB::transaction(function () use ($session, $declaredCash, $notes, $actor): CashierSession {
            $session = CashierSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($session->status !== 'open') {
                throw ValidationException::withMessages(['session' => 'Session imefungwa au haiwezi kufungwa.']);
            }

            $expected = $this->calculateExpectedCash($session);
            $variance = round($declaredCash - $expected, 2);

            $session->update([
                'status' => $variance == 0.0 ? 'closed' : 'variance_review',
                'closed_at' => now(),
                'expected_cash' => $expected,
                'declared_cash' => $declaredCash,
                'variance' => $variance,
                'notes' => $notes,
                'closed_by' => $actor->id,
            ]);

            $this->audit->record($variance == 0.0 ? 'cashier_session_closed' : 'cashier_session_variance_recorded', $session, [
                'session_id' => $session->id,
                'session_number' => $session->session_number,
                'cashier_user_id' => $session->user_id,
                'facility_id' => $session->facility_id,
                'expected_cash' => $expected,
                'declared_cash' => $declaredCash,
                'variance' => $variance,
                'timestamp' => now()->toISOString(),
            ]);

            return $session->refresh();
        });
    }

    private function normalizeOpeningFloat(string|float|int|null $openingFloat): string
    {
        $amount = (float) ($openingFloat ?: 0);

        if ($amount < 0) {
            throw ValidationException::withMessages(['opening_float' => 'Opening float haiwezi kuwa chini ya sifuri.']);
        }

        if (! $this->setting('billing_allow_zero_float', true) && $amount <= 0) {
            throw ValidationException::withMessages(['opening_float' => 'Opening float lazima iwe zaidi ya sifuri.']);
        }

        return number_format($amount, 2, '.', '');
    }

    private function normalizeShift(string $shift): string
    {
        $shift = strtolower(trim($shift));
        $allowed = ['morning', 'afternoon', 'evening', 'night', 'custom'];

        if (! in_array($shift, $allowed, true)) {
            throw ValidationException::withMessages(['shift' => 'Chagua shift sahihi.']);
        }

        return $shift;
    }

    private function setting(string $key, bool $default): bool
    {
        $value = FacilitySetting::query()
            ->where('facility_id', currentFacility()?->id)
            ->where('key', $key)
            ->value('value');

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
