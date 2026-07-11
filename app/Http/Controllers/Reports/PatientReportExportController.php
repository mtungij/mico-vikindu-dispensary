<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Patient;

class PatientReportExportController extends Controller
{
    public function __invoke()
    {
        abort_unless(auth()->user()->can('patients.export') || auth()->user()->can('reports.view'), 403);
        $rows = Patient::query()->forCurrentFacility()->with('primaryPayerProfile')->get();
        return response()->streamDownload(function () use ($rows): void { $h = fopen('php://output','w'); fputcsv($h, ['Number','Name','Gender','Phone','Payer','Status']); foreach($rows as $p){ fputcsv($h, [$p->patient_number,$p->fullName(),$p->gender?->value,$p->primary_phone,$p->primaryPayerProfile?->payer_type?->value,$p->patient_status?->value]); } fclose($h); }, 'patients-report.csv', ['Content-Type'=>'text/csv']);
    }
}
