<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\MedicineBatch;
use App\Models\StockMovement;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PharmacyReportExportController extends Controller
{
    public function __invoke(string $type = 'stock-movement'): Response
    {
        Gate::authorize('pharmacy.reports.export');

        $rows = match ($type) {
            'expiry-report' => MedicineBatch::query()->forCurrentFacility()->with(['medicine', 'location'])->latest('expiry_date')->limit(1000)->get()
                ->map(fn ($batch) => [$batch->medicine?->name, $batch->batch_number, $batch->location?->name, $batch->expiry_date?->format('Y-m-d'), $batch->available_quantity]),
            default => StockMovement::query()->forCurrentFacility()->with(['medicine', 'batch', 'location'])->latest('occurred_at')->limit(1000)->get()
                ->map(fn ($movement) => [$movement->occurred_at?->format('Y-m-d H:i'), $movement->medicine?->name, $movement->batch?->batch_number, $movement->location?->name, $movement->movement_type?->value ?? $movement->movement_type, $movement->quantity]),
        };

        $header = $type === 'expiry-report'
            ? ['Medicine', 'Batch', 'Location', 'Expiry Date', 'Available Qty']
            : ['Date', 'Medicine', 'Batch', 'Location', 'Movement', 'Quantity'];

        $csv = collect([$header])->merge($rows)->map(fn ($row) => collect($row)->map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"')->implode(','))->implode("\n");

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="pharmacy-'.$type.'.csv"',
        ]);
    }
}
