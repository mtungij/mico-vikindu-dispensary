<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\BillingReportService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BillingReportExportController extends Controller
{
    public function __invoke(string $type, BillingReportService $reports): Response
    {
        Gate::authorize('billing.reports.export');

        if ($type === 'cashiers') {
            $rows = $reports->paymentsByCashier()->limit(1000)->get()->map(fn ($row): array => [
                $row->receivedBy?->name ?? 'Unknown',
                $row->payments_count,
                number_format((float) $row->total_amount, 2, '.', ''),
                $row->first_payment_at,
                $row->last_payment_at,
            ]);

            $headings = ['Cashier', 'Payments', 'Total Amount', 'First Payment', 'Last Payment'];
        } else {
            $rows = $reports->payments()->latest()->limit(1000)->get()->map(fn ($payment): array => [
                $payment->payment_number,
                $payment->payment_date?->format('Y-m-d H:i'),
                $payment->invoice?->invoice_number,
                trim(($payment->invoice?->patient?->first_name ?? '').' '.($payment->invoice?->patient?->last_name ?? '')),
                $payment->method?->name,
                $payment->receivedBy?->name,
                $payment->transaction_reference,
                $payment->status,
                number_format((float) $payment->amount, 2, '.', ''),
            ]);

            $headings = ['Payment No', 'Date', 'Invoice', 'Patient', 'Method', 'Received By', 'Reference', 'Status', 'Amount'];
        }

        $csv = collect([$headings])
            ->merge($rows)
            ->map(fn ($row): string => collect($row)->map(fn ($value): string => '"'.str_replace('"', '""', (string) $value).'"')->implode(','))
            ->implode("\n");

        return response($csv."\n", 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="billing-'.$type.'-'.now()->format('YmdHis').'.csv"',
        ]);
    }
}
