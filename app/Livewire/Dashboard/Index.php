<?php

namespace App\Livewire\Dashboard;

use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Facility;
use App\Models\StaffProfile;
use App\Models\StaffProfessionalLicense;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\PatientQueue;
use App\Models\Service;
use App\Models\ClinicalAlert;
use App\Models\ClinicalEncounter;
use App\Models\LaboratoryCriticalResultNotification;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;
use App\Models\LaboratorySample;
use App\Models\Prescription;
use App\Models\PatientReferral;
use App\Models\Appointment;
use App\Models\Bed;
use App\Models\IvFluidAdministration;
use App\Models\MedicationAdministration;
use App\Models\NursingTask;
use App\Models\ObservationAdmission;
use App\Services\FacilitySetupService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    /**
     * @return array<int, array<string, string|int>>
     */
    public function stats(): array
    {
        return [
            ['label' => 'Wagonjwa Leo', 'value' => 0, 'icon' => 'users', 'tone' => 'teal'],
            ['label' => 'Total Staff', 'value' => StaffProfile::query()->forCurrentFacility()->count(), 'icon' => 'users', 'tone' => 'blue'],
            ['label' => 'Patients Today', 'value' => Patient::query()->forCurrentFacility()->whereDate('registered_at', today())->count(), 'icon' => 'user-plus', 'tone' => 'green'],
            ['label' => 'Active Visits', 'value' => Visit::query()->forCurrentFacility()->whereNotIn('visit_status', ['completed','cancelled','discharged'])->count(), 'icon' => 'clipboard-list', 'tone' => 'amber'],
            ['label' => 'Awaiting Payment', 'value' => Visit::query()->forCurrentFacility()->where('visit_status', 'awaiting_payment')->count(), 'icon' => 'receipt', 'tone' => 'red'],
            ['label' => 'Reception Queue', 'value' => PatientQueue::query()->forCurrentFacility()->whereDate('queue_date', today())->where('queue_status', 'waiting')->count(), 'icon' => 'list-ordered', 'tone' => 'indigo'],
            ['label' => 'Awaiting Triage', 'value' => Visit::query()->forCurrentFacility()->where('visit_status', 'awaiting_triage')->count(), 'icon' => 'heart-pulse', 'tone' => 'amber'],
            ['label' => 'OPD Waiting', 'value' => Visit::query()->forCurrentFacility()->whereIn('visit_status', ['awaiting_department', 'in_queue'])->count(), 'icon' => 'stethoscope', 'tone' => 'blue'],
            ['label' => 'In Consultation', 'value' => Visit::query()->forCurrentFacility()->where('visit_status', 'in_consultation')->count(), 'icon' => 'activity', 'tone' => 'teal'],
            ['label' => 'Lab Orders Today', 'value' => LaboratoryOrder::query()->forCurrentFacility()->whereDate('ordered_at', today())->count(), 'icon' => 'flask-conical', 'tone' => 'indigo'],
            ['label' => 'Awaiting Samples', 'value' => LaboratoryOrder::query()->forCurrentFacility()->where('status', 'sample_pending')->count(), 'icon' => 'test-tube', 'tone' => 'amber'],
            ['label' => 'Results Pending', 'value' => LaboratoryResult::query()->forCurrentFacility()->whereIn('result_status', ['draft', 'entered'])->count(), 'icon' => 'file-pen-line', 'tone' => 'blue'],
            ['label' => 'Awaiting Verification', 'value' => LaboratoryResult::query()->forCurrentFacility()->where('result_status', 'pending_verification')->count(), 'icon' => 'file-check-2', 'tone' => 'indigo'],
            ['label' => 'Samples Rejected', 'value' => LaboratorySample::query()->forCurrentFacility()->whereDate('rejected_at', today())->count(), 'icon' => 'circle-x', 'tone' => 'red'],
            ['label' => 'Completed Tests Today', 'value' => LaboratoryResult::query()->forCurrentFacility()->whereDate('released_at', today())->count(), 'icon' => 'check-circle-2', 'tone' => 'green'],
            ['label' => 'Critical Lab Results', 'value' => LaboratoryCriticalResultNotification::query()->forCurrentFacility()->whereIn('status', ['pending', 'notified'])->count(), 'icon' => 'triangle-alert', 'tone' => 'red'],
            ['label' => 'Prescriptions Today', 'value' => Prescription::query()->forCurrentFacility()->whereDate('prescribed_at', today())->count(), 'icon' => 'pill', 'tone' => 'green'],
            ['label' => 'Critical Alerts', 'value' => ClinicalAlert::query()->forCurrentFacility()->where('severity', 'critical')->whereIn('status', ['active', 'acknowledged'])->count(), 'icon' => 'triangle-alert', 'tone' => 'red'],
            ['label' => 'Referrals Today', 'value' => PatientReferral::query()->forCurrentFacility()->whereDate('referred_at', today())->count(), 'icon' => 'send', 'tone' => 'blue'],
            ['label' => 'Follow-ups Due', 'value' => Appointment::query()->forCurrentFacility()->whereDate('scheduled_start', today())->count(), 'icon' => 'calendar-clock', 'tone' => 'green'],
            ['label' => 'Available Beds', 'value' => Bed::query()->forCurrentFacility()->where('status', 'available')->count(), 'icon' => 'bed-single', 'tone' => 'green'],
            ['label' => 'Occupied Beds', 'value' => Bed::query()->forCurrentFacility()->where('status', 'occupied')->count(), 'icon' => 'bed', 'tone' => 'amber'],
            ['label' => 'Under Observation', 'value' => ObservationAdmission::query()->forCurrentFacility()->whereIn('status', ['admitted', 'under_observation'])->count(), 'icon' => 'heart-pulse', 'tone' => 'teal'],
            ['label' => 'Observation Awaiting Bed', 'value' => ObservationAdmission::query()->forCurrentFacility()->where('status', 'awaiting_bed')->count(), 'icon' => 'clock', 'tone' => 'amber'],
            ['label' => 'Medication Due', 'value' => MedicationAdministration::query()->forCurrentFacility()->whereIn('administration_status', ['scheduled', 'due', 'late'])->where('scheduled_at', '<=', now())->count(), 'icon' => 'pill', 'tone' => 'red'],
            ['label' => 'Overdue Nursing Tasks', 'value' => NursingTask::query()->forCurrentFacility()->whereNotNull('due_at')->where('due_at', '<', now())->whereNotIn('status', ['completed', 'cancelled'])->count(), 'icon' => 'clipboard-list', 'tone' => 'red'],
            ['label' => 'IV Fluids Running', 'value' => IvFluidAdministration::query()->forCurrentFacility()->where('status', 'running')->count(), 'icon' => 'droplets', 'tone' => 'blue'],
        ];
    }

    public function setupProgress(): array
    {
        $setup = app(FacilitySetupService::class);
        $facility = $setup->getCurrentFacility();
        $user = auth()->user();

        return [
            ['label' => 'Facility Setup', 'completed' => (bool) $facility?->isSetupCompleted(), 'route' => 'facility.setup'],
            ['label' => 'Departments', 'completed' => Department::query()->forCurrentFacility()->where('is_active', true)->exists(), 'route' => $user?->can('departments.view') ? 'settings.departments.index' : null],
            ['label' => 'Job Titles', 'completed' => JobTitle::query()->forCurrentFacility()->where('is_active', true)->exists(), 'route' => $user?->can('job-titles.view') ? 'settings.job-titles.index' : null],
            ['label' => 'Roles', 'completed' => Role::query()->forCurrentFacility()->exists(), 'route' => $user?->can('roles.view') ? 'settings.roles.index' : null],
            ['label' => 'Permissions', 'completed' => Permission::query()->exists(), 'route' => $user?->can('permissions.view') ? 'settings.permissions.index' : null],
            ['label' => 'Staff Users', 'completed' => StaffProfile::query()->forCurrentFacility()->whereHas('user', fn ($query) => $query->where('is_super_admin', false))->exists(), 'route' => $user?->can('staff.view') ? 'staff.index' : null],
            ['label' => 'Services', 'completed' => Service::query()->forCurrentFacility()->exists(), 'route' => $user?->can('services.view') ? 'settings.services.index' : null],
            ['label' => 'Patients', 'completed' => Patient::query()->forCurrentFacility()->exists(), 'route' => $user?->can('patients.view') ? 'patients.index' : null],
            ['label' => 'Reception', 'completed' => Visit::query()->forCurrentFacility()->exists(), 'route' => $user?->can('reception.access') ? 'reception.index' : null],
            ['label' => 'Clinical Workflow', 'completed' => true, 'route' => $user?->can('opd.access') ? 'opd.dashboard' : null],
            ['label' => 'Laboratory Setup', 'completed' => \App\Models\LaboratoryTest::query()->forCurrentFacility()->where('is_active', true)->exists(), 'route' => $user?->can('laboratory.manage-tests') ? 'settings.laboratory.tests' : null],
            ['label' => 'Observation Rooms/Beds', 'completed' => Bed::query()->forCurrentFacility()->where('is_active', true)->exists(), 'route' => $user?->can('observation.manage-beds') ? 'settings.observation.beds' : null],
        ];
    }

    public function quickActions(): array
    {
        return array_values(array_filter([
            auth()->user()->can('departments.create') ? ['label' => 'Ongeza Department', 'icon' => 'panels-top-left', 'route' => 'settings.departments.index'] : null,
            auth()->user()->can('job-titles.create') ? ['label' => 'Ongeza Job Title', 'icon' => 'badge', 'route' => 'settings.job-titles.index'] : null,
            auth()->user()->can('roles.create') ? ['label' => 'Ongeza Role', 'icon' => 'shield', 'route' => 'settings.roles.index'] : null,
            auth()->user()->can('roles.assign-permissions') ? ['label' => 'Manage Permissions', 'icon' => 'key-round', 'route' => 'settings.permissions.index'] : null,
            auth()->user()->can('staff.create') ? ['label' => 'Ongeza Mtumishi', 'icon' => 'user-plus', 'route' => 'staff.index'] : null,
            auth()->user()->can('services.create') ? ['label' => 'Ongeza Huduma', 'icon' => 'heart-pulse', 'route' => 'settings.services.index'] : null,
            auth()->user()->can('patients.create') ? ['label' => 'Sajili Mgonjwa', 'icon' => 'user-plus', 'route' => 'patients.index'] : null,
            auth()->user()->can('reception.open-visit') ? ['label' => 'Fungua Visit', 'icon' => 'clipboard-list', 'route' => 'patients.index'] : null,
            auth()->user()->can('triage.record-vitals') ? ['label' => 'Record Triage', 'icon' => 'heart-pulse', 'route' => 'triage.index'] : null,
            auth()->user()->can('opd.start-consultation') ? ['label' => 'Start Consultation', 'icon' => 'stethoscope', 'route' => 'opd.index'] : null,
            auth()->user()->can('laboratory.view-queue') ? ['label' => 'Lab Queue', 'icon' => 'flask-conical', 'route' => 'laboratory.index'] : null,
            auth()->user()->can('laboratory.manage-tests') ? ['label' => 'Setup Lab Tests', 'icon' => 'test-tube', 'route' => 'settings.laboratory.tests'] : null,
            auth()->user()->can('referrals.create') ? ['label' => 'Create Referral', 'icon' => 'send', 'route' => 'opd.index'] : null,
            auth()->user()->can('observation.admit') ? ['label' => 'Admit to Bed Rest', 'icon' => 'bed', 'route' => 'observation.bed-board'] : null,
            auth()->user()->can('observation.view-bed-board') ? ['label' => 'Bed Board', 'icon' => 'layout-grid', 'route' => 'observation.bed-board'] : null,
            auth()->user()->can('observation.discharge') ? ['label' => 'Observation Queue', 'icon' => 'heart-pulse', 'route' => 'observation.index'] : null,
        ]));
    }

    public function departmentStatuses(): array
    {
        return Department::query()->forCurrentFacility()->orderBy('sort_order')->limit(4)->get()->all();
    }

    public function render(): View
    {
        return view('livewire.dashboard.index')
            ->layout('components.layouts.app', [
                'title' => 'Dashibodi',
                'description' => 'Muhtasari wa mwanzo wa mfumo wa dispensary.',
            ]);
    }
}
