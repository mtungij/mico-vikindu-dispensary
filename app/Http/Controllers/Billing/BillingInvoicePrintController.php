<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BillingInvoicePrintController extends Controller
{
    public function __invoke(Invoice $invoice): View
    {
        Gate::authorize('billing.print-invoice');

        abort_unless($invoice->facility_id === currentFacility()?->id, 404);

        return view('billing.print.invoice', [
            'invoice' => $invoice->load(['patient', 'visit', 'items.service', 'payments.method']),
            'facility' => currentFacility(),
        ]);
    }
}
