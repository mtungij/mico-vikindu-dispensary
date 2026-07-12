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

        $rows = $reports->payments()->latest()->limit(1000)->get()->map(fn ($payment): array => [
            $payment->payment_number,
            $payment->payment_date?->format('Y-m-d H:i'),
            $payment->invoice?->invoice_number,
            trim(($payment->invoice?->patient?->first_name ?? '').' '.($payment->invoice?->patient?->last_name ?? '')),
            $payment->method?->name,
            $payment->transaction_reference,
            $payment->status,
            number_format((float) $payment->amount, 2, '.', ''),
        ]);

        $csv = collect([['Payment No', 'Date', 'Invoice', 'Patient', 'Method', 'Reference', 'Status', 'Amount']])
            ->merge($rows)
            ->map(fn ($row): string => collect($row)->map(fn ($value): string => '"'.str_replace('"', '""', (string) $value).'"')->implode(','))
            ->implode("\n");

        return response($csv."\n", 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="billing-'.$type.'-'.now()->format('YmdHis').'.csv"',
        ]);
    }
}
