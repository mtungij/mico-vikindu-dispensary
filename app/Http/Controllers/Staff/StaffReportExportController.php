<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffProfile;

class StaffReportExportController extends Controller
{
    public function __invoke()
    {
        abort_unless(auth()->user()->can('staff.export') || auth()->user()->can('reports.view'), 403);

        $rows = StaffProfile::query()
            ->forCurrentFacility()
            ->with(['user', 'employmentRecord.primaryDepartment', 'employmentRecord.jobTitle'])
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Employee Number', 'Name', 'Email', 'Department', 'Job Title', 'Employment Status', 'Account Status']);
            foreach ($rows as $profile) {
                fputcsv($handle, [
                    (string) $profile->employee_number,
                    (string) $profile->fullName(),
                    (string) $profile->user->email,
                    (string) ($profile->employmentRecord?->primaryDepartment?->name ?? ''),
                    (string) ($profile->employmentRecord?->jobTitle?->name ?? ''),
                    (string) ($profile->employmentRecord?->employment_status?->value ?? ''),
                    (string) ($profile->user->status?->value ?? ''),
                ]);
            }
            fclose($handle);
        }, 'staff-report.csv', ['Content-Type' => 'text/csv']);
    }
}
