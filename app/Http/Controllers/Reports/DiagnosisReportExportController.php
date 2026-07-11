<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Diagnosis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiagnosisReportExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'code', 'diagnosis', 'type', 'certainty', 'patient_id']);
            Diagnosis::query()->forCurrentFacility()->latest('diagnosed_at')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, [$row->diagnosed_at?->toDateTimeString(), $row->icd10_code, $row->diagnosis_name, $row->diagnosis_type->value, $row->certainty->value, $row->patient_id]);
                }
            });
        }, 'diagnoses-report.csv');
    }
}
