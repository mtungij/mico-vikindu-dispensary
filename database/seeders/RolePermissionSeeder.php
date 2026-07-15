<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $all = Permission::query()->pluck('name')->all();

        foreach ($this->map() as $roleName => $prefixes) {
            $role = Role::query()->where('name', $roleName)->first();
            if (! $role || $roleName === 'super-admin') {
                continue;
            }

            $permissions = collect($all)->filter(function (string $permission) use ($prefixes): bool {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($permission, $prefix)) {
                        return true;
                    }
                }
                return false;
            })->values()->all();

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function map(): array
    {
        return [
            'administrator' => ['dashboard.', 'facility.', 'departments.', 'job-titles.', 'roles.', 'permissions.', 'service-categories.', 'services.', 'insurance-providers.', 'corporate-accounts.', 'patients.', 'reception.', 'invoices.', 'staff.', 'reports.', 'settings.', 'audit-logs.', 'workflow.', 'laboratory.', 'laboratory-results.', 'laboratory-critical-results.', 'laboratory-outsourced-tests.', 'laboratory-reports.', 'pharmacy.', 'observation.', 'dental.', 'insurance.', 'appointments.', 'rch.'],
            'facility-manager' => ['dashboard.', 'departments.view', 'departments.update', 'job-titles.view', 'service-categories.view', 'services.view', 'patients.view', 'reception.access', 'reports.', 'staff.view', 'staff.create', 'staff.update', 'staff.assign-department', 'staff.manage-employment', 'staff.manage-education', 'staff.manage-license', 'staff.manage-documents', 'staff.manage-emergency-contacts', 'staff.export'],
            'receptionist' => ['dashboard.', 'patients.view', 'patients.create', 'patients.update', 'patients.manage-payers', 'patients.manage-documents', 'patients.print-card', 'reception.', 'workflow.manage-reception-queue', 'workflow.transfer-patient', 'workflow.print-ticket', 'invoices.view', 'invoices.create', 'billing.access', 'billing.view-queue', 'billing.view-invoice', 'observation.access', 'observation.view-bed-board', 'dental.access', 'dental.view-queue', 'appointments.access', 'appointments.view-dashboard', 'appointments.view', 'appointments.view-calendar', 'appointments.create', 'appointments.update', 'appointments.cancel', 'appointments.reschedule', 'appointments.check-in', 'rch.access', 'rch.view-dashboard', 'rch.view-queue', 'rch.pregnancies.view', 'rch.family-planning.view', 'rch.children.view', 'rch.immunization.view', 'rch.appointments.'],
            'cashier' => ['dashboard.', 'patients.view', 'invoices.view', 'billing.access', 'billing.view-', 'billing.receive-', 'billing.confirm-payment', 'billing.reprint-receipt', 'billing.print-invoice', 'billing.reports.view', 'cashier.sessions.', 'workflow.manage-billing-queue', 'observation.view-billing', 'dental.view-billing', 'rch.billing.view'],
            'accountant' => ['dashboard.', 'accounting.', 'billing.', 'reports.view', 'reports.view-financial', 'insurance.access', 'insurance.view-dashboard', 'insurance.claims.view', 'insurance.payments.', 'insurance.reconciliation.manage', 'insurance.reports.', 'insurance.view-nhif-report', 'insurance.view-financial-totals'],
            'doctor' => ['dashboard.', 'patients.view', 'opd.', 'workflow.manage-opd-queue', 'workflow.transfer-patient', 'workflow.emergency-override', 'laboratory-results.view', 'laboratory-results.release', 'laboratory-results.print', 'pharmacy.view-prescription', 'pharmacy.view-stock', 'bed-rest.admit', 'bed-rest.discharge', 'bed-rest.refer', 'observation.access', 'observation.view-', 'observation.admit', 'observation.create-order', 'observation.clinical-review', 'observation.discharge', 'observation.refer', 'observation.print-', 'appointments.access', 'appointments.view-dashboard', 'appointments.view', 'appointments.view-calendar', 'appointments.create', 'appointments.update', 'appointments.reschedule', 'appointments.check-in', 'appointments.manage-doctor-schedules', 'rch.access', 'rch.view-dashboard', 'rch.view-queue', 'rch.start-encounter', 'rch.complete-encounter', 'rch.view-history', 'rch.pregnancies.', 'rch.anc.', 'rch.family-planning.view', 'rch.children.view', 'rch.growth.view', 'rch.immunization.view', 'rch.reports.view'],
            'clinical-officer' => ['dashboard.', 'patients.view', 'opd.', 'laboratory-results.view', 'laboratory-results.release', 'laboratory-results.print', 'pharmacy.view-prescription', 'pharmacy.view-stock', 'observation.access', 'observation.view-', 'observation.admit', 'observation.create-order', 'observation.clinical-review', 'observation.discharge', 'rch.access', 'rch.view-dashboard', 'rch.view-queue', 'rch.start-encounter', 'rch.complete-encounter', 'rch.pregnancies.view', 'rch.anc.view', 'rch.anc.record-visit', 'rch.children.view', 'rch.growth.view', 'rch.immunization.view'],
            'nurse' => ['dashboard.', 'patients.view', 'triage.', 'workflow.manage-triage-queue', 'workflow.manage-bed-queue', 'bed-rest.record-nursing-care', 'bed-rest.administer-medication', 'observation.access', 'observation.view-bed-board', 'observation.view-admission', 'observation.record-nursing-observation', 'observation.administer-medication', 'observation.record-iv-fluid', 'observation.record-oxygen', 'observation.record-nebulization', 'observation.record-intake-output', 'observation.manage-nursing-tasks', 'observation.create-handover', 'observation.acknowledge-handover', 'observation.manage-bed-cleaning'],
            'laboratory-technician' => ['dashboard.', 'workflow.manage-laboratory-queue', 'laboratory.', 'laboratory-results.', 'laboratory-critical-results.', 'laboratory-outsourced-tests.', 'laboratory-reports.'],
            'pharmacist' => ['dashboard.', 'workflow.manage-pharmacy-queue', 'pharmacy.'],
            'dentist' => ['dashboard.', 'patients.view', 'workflow.manage-dental-queue', 'dental.', 'prescriptions.view', 'prescriptions.create', 'appointments.', 'referrals.create'],
            'rch-nurse' => ['dashboard.', 'workflow.manage-rch-queue', 'rch.', 'appointments.access', 'appointments.view-dashboard', 'appointments.view', 'appointments.view-calendar', 'appointments.create', 'appointments.update', 'appointments.reschedule', 'appointments.check-in'],
            'vaccination-nurse' => ['dashboard.', 'patients.view', 'rch.access', 'rch.view-dashboard', 'rch.view-queue', 'rch.children.view', 'rch.growth.view', 'rch.immunization.', 'rch.vaccines.view-batches', 'appointments.access', 'appointments.view', 'appointments.create'],
            'store-keeper' => ['dashboard.', 'pharmacy.view-stock', 'pharmacy.receive-stock', 'pharmacy.transfer-stock', 'pharmacy.stock-count', 'pharmacy.adjust-stock', 'pharmacy.view-stock-card'],
            'records-officer' => ['dashboard.', 'patients.view', 'reports.view', 'staff.view', 'staff.manage-documents', 'staff.view-login-history'],
        ];
    }
}
