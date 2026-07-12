<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\CashierSession;
use App\Services\CashierSessionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CashierSessionPrintController extends Controller
{
    public function __invoke(CashierSession $cashierSession, CashierSessionService $sessions): View
    {
        Gate::authorize('view', $cashierSession);

        return view('billing.print.cashier-session', [
            'session' => $cashierSession->load(['cashier', 'payments.method', 'payments.invoice.patient']),
            'expected' => $sessions->calculateExpectedCash($cashierSession),
            'facility' => currentFacility(),
        ]);
    }
}
