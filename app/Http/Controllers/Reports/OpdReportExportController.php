<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ClinicalEncounter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpdReportExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'encounter', 'patient_id', 'provider', 'status', 'outcome']);
            ClinicalEncounter::query()->forCurrentFacility()->with('provider')->latest()->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, [$row->started_at?->toDateTimeString(), $row->encounter_number, $row->patient_id, $row->provider?->name, $row->status->value, $row->outcome?->value]);
                }
            });
        }, 'opd-report.csv');
    }
}
