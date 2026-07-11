<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\DentalEncounter;
use App\Models\DentalProcedure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DentalReportExportController extends Controller
{
    public function __invoke(string $type): Response
    {
        Gate::authorize('dental.reports.export');
        $rows = str_contains($type, 'procedure') || in_array($type, ['materials','revenue','provider-workload'], true)
            ? DentalProcedure::query()->forCurrentFacility()->with('patient')->latest()->limit(1000)->get()
            : DentalEncounter::query()->forCurrentFacility()->with('patient')->latest()->limit(1000)->get();
        $csv = "record,patient,status,date\n";
        foreach ($rows as $row) {
            $csv .= implode(',', [str_replace(',', ' ', $row->procedure_number ?? $row->dental_encounter_number ?? $row->id), str_replace(',', ' ', $row->patient?->fullName() ?? ''), $row->status?->value ?? $row->status ?? '', $row->created_at?->toDateString()])."\n";
        }
        return response($csv, 200, ['Content-Type'=>'text/csv', 'Content-Disposition'=>"attachment; filename=dental-{$type}.csv"]);
    }
}
