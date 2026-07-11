<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\TriageAssessment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TriageReportExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'patient_id', 'visit_id', 'level', 'status', 'nurse']);
            TriageAssessment::query()->forCurrentFacility()->with('assessor')->latest('assessed_at')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, [$row->assessed_at?->toDateTimeString(), $row->patient_id, $row->visit_id, $row->triage_level->value, $row->status->value, $row->assessor?->name]);
                }
            });
        }, 'triage-report.csv');
    }
}
