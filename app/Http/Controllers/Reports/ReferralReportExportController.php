<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\PatientReferral;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReferralReportExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'referral', 'destination', 'urgency', 'status', 'referrer']);
            PatientReferral::query()->forCurrentFacility()->latest('referred_at')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, [$row->referred_at?->toDateTimeString(), $row->referral_number, $row->destination_facility_name, $row->urgency, $row->status->value, $row->referred_by]);
                }
            });
        }, 'referrals-report.csv');
    }
}
