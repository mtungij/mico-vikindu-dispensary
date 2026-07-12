<?php

namespace App\Http\Controllers\Reports;

use App\Services\InsuranceAuditService;
use App\Services\InsuranceReportService;
use Illuminate\Support\Facades\Gate;

class InsuranceReportExportController
{
    public function __invoke(string $type, InsuranceReportService $reports, InsuranceAuditService $audit)
    {
        Gate::authorize('insurance.reports.export');
        $audit->record($type === 'nhif-claim-report' ? 'nhif_claim_report_generated' : 'insurance_report_exported', null, ['type' => $type]);

        return response()->streamDownload(function () use ($reports): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Claim Number','Patient','Membership','Provider','Service From','Gross','Patient Portion','Claimed','Approved','Rejected','Paid','Outstanding','Status']);
            $reports->claimsQuery()->chunk(200, function ($claims) use ($out): void {
                foreach ($claims as $claim) {
                    fputcsv($out, [
                        $claim->claim_number,
                        trim(($claim->patient?->first_name ?? '').' '.($claim->patient?->last_name ?? '')),
                        $claim->membership?->membership_number,
                        $claim->provider?->name,
                        $claim->service_date_from?->format('Y-m-d'),
                        $claim->gross_amount,
                        $claim->patient_amount,
                        $claim->payer_claimed_amount,
                        $claim->approved_amount,
                        $claim->rejected_amount,
                        $claim->paid_amount,
                        $claim->outstanding_amount,
                        $claim->status,
                    ]);
                }
            });
            fclose($out);
        }, 'insurance-'.$type.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
