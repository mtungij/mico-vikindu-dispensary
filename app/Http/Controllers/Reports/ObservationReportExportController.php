<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\ObservationReportService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ObservationReportExportController extends Controller
{
    public function __invoke(string $type): Response
    {
        Gate::authorize('observation.reports.export');
        $reports = app(ObservationReportService::class);
        $rows = match ($type) { 'nursing' => $reports->nursing()->limit(1000)->get(), 'medication-administration' => $reports->medication()->limit(1000)->get(), 'discharges' => $reports->discharges()->limit(1000)->get(), 'bed-cleaning' => $reports->cleaning()->limit(1000)->get(), default => $reports->admissions()->limit(1000)->get() };
        $csv = collect([['ID','Record','Status','Date']])->merge($rows->map(fn($r)=>[$r->id, $r->admission_number ?? $r->bed?->code ?? $r->medicine_name_snapshot ?? $r->patient?->full_name ?? '', $r->status?->value ?? $r->status ?? $r->administration_status?->value ?? '', ($r->admitted_at ?? $r->recorded_at ?? $r->scheduled_at ?? $r->requested_at ?? $r->created_at)?->format('Y-m-d H:i')]))->map(fn($row)=>collect($row)->map(fn($v)=>'"'.str_replace('"','""',(string)$v).'"')->implode(','))->implode("\n");
        return response($csv, 200, ['Content-Type'=>'text/csv', 'Content-Disposition'=>'attachment; filename="observation-'.$type.'.csv"']);
    }
}
