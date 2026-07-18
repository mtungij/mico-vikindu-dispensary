<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class BillingReportService
{
    public function invoices(array $filters = [])
    {
        return Invoice::query()->forCurrentFacility()->with(['patient', 'visit', 'items'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
    }

    public function payments(array $filters = [])
    {
        return Payment::query()->forCurrentFacility()->with(['invoice.patient', 'method', 'receivedBy'])
            ->when($filters['method_id'] ?? null, fn ($q, $id) => $q->where('payment_method_id', $id));
    }

    public function paymentsByCashier(array $filters = [])
    {
        return Payment::query()
            ->forCurrentFacility()
            ->select('received_by')
            ->selectRaw('COUNT(*) as payments_count')
            ->selectRaw('SUM(amount) as total_amount')
            ->selectRaw('MIN(payment_date) as first_payment_at')
            ->selectRaw('MAX(payment_date) as last_payment_at')
            ->with('receivedBy')
            ->where('status', 'confirmed')
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('payment_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('payment_date', '<=', $date))
            ->groupBy('received_by')
            ->orderByDesc(DB::raw('SUM(amount)'));
    }
}
