<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\PatientQueue;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkflowReportExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('workflow.reports.export'), 403);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Queue Number', 'Department', 'Patient', 'Status', 'Waiting Minutes', 'Service Minutes', 'Date']);
            PatientQueue::query()->forCurrentFacility()->with(['department', 'patient'])->latest()->limit(500)->get()->each(function (PatientQueue $queue) use ($out): void {
                fputcsv($out, [
                    $queue->queue_number,
                    $queue->department?->name,
                    trim(($queue->patient?->first_name ?? '').' '.($queue->patient?->last_name ?? '')),
                    $queue->queue_status?->value ?? $queue->queue_status,
                    $queue->waiting_seconds ? round($queue->waiting_seconds / 60, 1) : 0,
                    $queue->service_seconds ? round($queue->service_seconds / 60, 1) : 0,
                    $queue->queue_date?->toDateString(),
                ]);
            });
            fclose($out);
        }, 'workflow-report.csv', ['Content-Type' => 'text/csv']);
    }
}
