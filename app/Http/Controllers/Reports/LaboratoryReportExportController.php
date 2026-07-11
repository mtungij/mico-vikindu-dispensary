<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\LaboratoryCriticalResultNotification;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;
use App\Models\LaboratorySample;
use App\Models\LaboratoryTest;
use App\Services\LaboratoryTurnaroundTimeService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaboratoryReportExportController extends Controller
{
    public function __invoke(string $type, LaboratoryTurnaroundTimeService $tat): StreamedResponse
    {
        abort_unless(in_array($type, ['orders', 'tests', 'samples', 'results', 'critical-results', 'revenue', 'turnaround-time'], true), 404);

        return response()->streamDownload(function () use ($type, $tat): void {
            $out = fopen('php://output', 'w');

            match ($type) {
                'orders' => $this->orders($out),
                'tests' => $this->tests($out),
                'samples' => $this->samples($out),
                'results' => $this->results($out),
                'critical-results' => $this->criticalResults($out),
                'revenue' => $this->revenue($out),
                'turnaround-time' => $this->turnaroundTime($out, $tat),
            };
        }, "laboratory-{$type}.csv", ['Content-Type' => 'text/csv']);
    }

    private function orders($out): void
    {
        fputcsv($out, ['date', 'order_number', 'patient', 'status', 'items']);
        LaboratoryOrder::query()->forCurrentFacility()->with(['patient', 'items'])->latest('ordered_at')->chunk(500, function ($rows) use ($out): void {
            foreach ($rows as $row) {
                fputcsv($out, [$row->ordered_at?->toDateTimeString(), $row->order_number, $row->patient?->fullName(), $row->status?->value, $row->items->count()]);
            }
        });
    }

    private function tests($out): void
    {
        fputcsv($out, ['code', 'name', 'category', 'specimen', 'active', 'outsourced']);
        LaboratoryTest::query()->forCurrentFacility()->with(['category', 'specimenType'])->orderBy('name')->chunk(500, function ($rows) use ($out): void {
            foreach ($rows as $row) {
                fputcsv($out, [$row->code, $row->name, $row->category?->name, $row->specimenType?->name, $row->is_active ? 'yes' : 'no', $row->is_outsourced ? 'yes' : 'no']);
            }
        });
    }

    private function samples($out): void
    {
        fputcsv($out, ['collected_at', 'sample_number', 'patient_id', 'specimen', 'status', 'quality']);
        LaboratorySample::query()->forCurrentFacility()->with('specimenType')->latest('collected_at')->chunk(500, function ($rows) use ($out): void {
            foreach ($rows as $row) {
                fputcsv($out, [$row->collected_at?->toDateTimeString(), $row->sample_number, $row->patient_id, $row->specimenType?->name, $row->sample_status?->value, $row->quality_status?->value]);
            }
        });
    }

    private function results($out): void
    {
        fputcsv($out, ['entered_at', 'order_number', 'test', 'status', 'flag', 'verified_at', 'released_at']);
        LaboratoryResult::query()->forCurrentFacility()->with(['order', 'test'])->latest('entered_at')->chunk(500, function ($rows) use ($out): void {
            foreach ($rows as $row) {
                fputcsv($out, [$row->entered_at?->toDateTimeString(), $row->order?->order_number, $row->test?->name, $row->result_status?->value, $row->abnormal_flag?->value, $row->verified_at?->toDateTimeString(), $row->released_at?->toDateTimeString()]);
            }
        });
    }

    private function criticalResults($out): void
    {
        fputcsv($out, ['notified_at', 'order_number', 'test', 'status', 'method', 'notes']);
        LaboratoryCriticalResultNotification::query()->forCurrentFacility()->with(['result.order', 'result.test'])->latest('notified_at')->chunk(500, function ($rows) use ($out): void {
            foreach ($rows as $row) {
                fputcsv($out, [$row->notified_at?->toDateTimeString(), $row->result?->order?->order_number, $row->result?->test?->name, $row->status?->value, $row->notification_method, $row->communication_notes]);
            }
        });
    }

    private function revenue($out): void
    {
        fputcsv($out, ['date', 'order_number', 'patient', 'estimated_revenue']);
        LaboratoryOrder::query()->forCurrentFacility()->with(['patient', 'items.service.prices'])->latest('ordered_at')->chunk(500, function ($rows) use ($out): void {
            foreach ($rows as $row) {
                $amount = $row->items->sum(fn ($item) => (float) ($item->service?->prices->first()?->amount ?? 0));
                fputcsv($out, [$row->ordered_at?->toDateString(), $row->order_number, $row->patient?->fullName(), $amount]);
            }
        });
    }

    private function turnaroundTime($out, LaboratoryTurnaroundTimeService $tat): void
    {
        fputcsv($out, ['metric', 'value']);
        foreach ($tat->summary(currentFacility()) as $metric => $value) {
            fputcsv($out, [$metric, $value]);
        }
    }
}
