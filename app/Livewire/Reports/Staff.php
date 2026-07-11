<?php

namespace App\Livewire\Reports;

use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Role;
use App\Models\StaffProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Staff extends Component
{
    public string $department = '';
    public string $jobTitle = '';
    public string $role = '';
    public string $employmentStatus = '';
    public string $accountStatus = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('staff.export') || auth()->user()->can('reports.view'), 403);
    }

    public function exportCsv()
    {
        abort_unless(auth()->user()->can('staff.export') || auth()->user()->can('reports.view'), 403);

        $rows = $this->query()->get();
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="staff-report.csv"'];

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
        }, 'staff-report.csv', $headers);
    }

    public function render(): View
    {
        $profiles = $this->query()->get();

        return view('livewire.reports.staff', [
            'total' => $profiles->count(),
            'active' => $profiles->where('employmentRecord.employment_status.value', 'active')->count(),
            'genderCounts' => $profiles->groupBy(fn ($profile) => $profile->gender?->value ?? 'unknown')->map->count(),
            'departmentCounts' => $profiles->groupBy(fn ($profile) => $profile->employmentRecord?->primaryDepartment?->name ?? 'None')->map->count(),
            'categoryCounts' => $profiles->groupBy(fn ($profile) => $profile->employmentRecord?->employment_category?->value ?? 'unknown')->map->count(),
            'departments' => Department::query()->forCurrentFacility()->orderBy('name')->get(),
            'jobTitles' => JobTitle::query()->forCurrentFacility()->orderBy('name')->get(),
            'roles' => Role::query()->forCurrentFacility()->orderBy('display_name')->get(),
        ])->layout('components.layouts.app', [
            'title' => 'Staff Report',
            'description' => 'Muhtasari na CSV export ya watumishi.',
        ]);
    }

    private function query()
    {
        return StaffProfile::query()
            ->forCurrentFacility()
            ->with(['user.roles', 'employmentRecord.primaryDepartment', 'employmentRecord.jobTitle'])
            ->when($this->department !== '', fn ($query) => $query->whereHas('employmentRecord', fn ($query) => $query->where('primary_department_id', $this->department)))
            ->when($this->jobTitle !== '', fn ($query) => $query->whereHas('employmentRecord', fn ($query) => $query->where('job_title_id', $this->jobTitle)))
            ->when($this->role !== '', fn ($query) => $query->whereHas('user.roles', fn ($query) => $query->where('roles.id', $this->role)))
            ->when($this->employmentStatus !== '', fn ($query) => $query->whereHas('employmentRecord', fn ($query) => $query->where('employment_status', $this->employmentStatus)))
            ->when($this->accountStatus !== '', fn ($query) => $query->whereHas('user', fn ($query) => $query->where('status', $this->accountStatus)));
    }
}
