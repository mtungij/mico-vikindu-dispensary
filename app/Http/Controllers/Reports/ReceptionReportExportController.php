<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Visit;

class ReceptionReportExportController extends Controller
{
    public function __invoke()
    {
        abort_unless(auth()->user()->can('reports.view') || auth()->user()->can('patients.export'), 403);
        $rows = Visit::query()->forCurrentFacility()->with(['patient','destinationDepartment'])->get();
        return response()->streamDownload(function () use ($rows): void { $h = fopen('php://output','w'); fputcsv($h, ['Visit','Patient','Department','Payer','Status','Priority']); foreach($rows as $v){ fputcsv($h, [$v->visit_number,$v->patient->fullName(),$v->destinationDepartment?->name,$v->payer_type?->value,$v->visit_status?->value,$v->priority?->value]); } fclose($h); }, 'reception-report.csv', ['Content-Type'=>'text/csv']);
    }
}
