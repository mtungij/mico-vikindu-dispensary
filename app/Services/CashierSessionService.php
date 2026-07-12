<?php

namespace App\Services;

use App\Models\CashierSession;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashierSessionService
{
    public function __construct(private readonly BillingNumberService $numbers, private readonly BillingAuditService $audit) {}

    public function getActiveSession($cashier = null): ?CashierSession
    {
        $cashier ??= auth()->user();
        return CashierSession::query()->forCurrentFacility()->where('user_id', $cashier->id)->where('status', 'open')->latest()->first();
    }

    public function openSession($cashier, float $openingFloat = 0, ?string $notes = null): CashierSession
    {
        if ($this->getActiveSession($cashier)) {
            throw ValidationException::withMessages(['session' => 'Cashier tayari ana session iliyo wazi.']);
        }
        $session = CashierSession::query()->create([
            'facility_id' => currentFacility()->id,
            'user_id' => $cashier->id,
            'session_number' => $this->numbers->cashierSession(currentFacility()->id),
            'opened_at' => now(),
            'opening_float' => $openingFloat,
            'status' => 'open',
            'notes' => $notes,
            'opened_by' => auth()->id() ?? $cashier->id,
        ]);
        $this->audit->record('cashier_session_opened', $session);

        return $session;
    }

    public function calculateExpectedCash(CashierSession $session): float
    {
        $cashMethods = PaymentMethod::query()->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $session->facility_id))->where('is_cash', true)->pluck('id');
        $cash = $session->payments()->whereIn('payment_method_id', $cashMethods)->where('status', 'confirmed')->sum('amount');

        return round((float) $session->opening_float + (float) $cash, 2);
    }

    public function closeSession(CashierSession $session, float $declaredCash, ?string $notes, $actor): CashierSession
    {
        return DB::transaction(function () use ($session, $declaredCash, $notes, $actor): CashierSession {
            $session = CashierSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($session->status !== 'open') throw ValidationException::withMessages(['session' => 'Session imefungwa au haiwezi kufungwa.']);
            $expected = $this->calculateExpectedCash($session);
            $variance = round($declaredCash - $expected, 2);
            $session->update(['status' => $variance == 0.0 ? 'closed' : 'variance_review', 'closed_at' => now(), 'expected_cash' => $expected, 'declared_cash' => $declaredCash, 'variance' => $variance, 'notes' => $notes, 'closed_by' => $actor->id]);
            $this->audit->record($variance == 0.0 ? 'cashier_session_closed' : 'cashier_session_variance_recorded', $session);

            return $session->refresh();
        });
    }
}
