<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReceiptPrintController extends Controller
{
    public function __invoke(Receipt $receipt): View
    {
        Gate::authorize('print', $receipt);

        return view('billing.print.receipt', [
            'receipt' => $receipt->load(['invoice.patient', 'payment.method']),
            'facility' => currentFacility(),
        ]);
    }
}
