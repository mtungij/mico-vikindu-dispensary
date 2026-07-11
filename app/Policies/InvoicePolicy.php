<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool { return $user->can('invoices.view') && $invoice->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('invoices.create'); }
    public function cancel(User $user, Invoice $invoice): bool { return $user->can('invoices.cancel') && $invoice->facility_id === currentFacility()?->id; }
}
