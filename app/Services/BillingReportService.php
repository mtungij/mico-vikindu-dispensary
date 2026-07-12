<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;

class BillingReportService
{
    public function invoices(array $filters = [])
    {
        return Invoice::query()->forCurrentFacility()->with(['patient', 'visit', 'items'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
    }

    public function payments(array $filters = [])
    {
        return Payment::query()->forCurrentFacility()->with(['invoice.patient', 'method', 'cashierSession'])
            ->when($filters['method_id'] ?? null, fn ($q, $id) => $q->where('payment_method_id', $id));
    }
}
