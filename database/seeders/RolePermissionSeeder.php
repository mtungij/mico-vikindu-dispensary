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
            'administrator' => ['dashboard.', 'facility.', 'departments.', 'job-titles.', 'roles.', 'permissions.', 'service-categories.', 'services.', 'insurance-providers.', 'corporate-accounts.', 'patients.', 'reception.', 'invoices.', 'staff.', 'reports.', 'settings.', 'audit-logs.', 'laboratory.', 'laboratory-results.', 'laboratory-critical-results.', 'laboratory-outsourced-tests.', 'laboratory-reports.', 'pharmacy.', 'observation.', 'dental.'],
            'facility-manager' => ['dashboard.', 'departments.view', 'departments.update', 'job-titles.view', 'service-categories.view', 'services.view', 'patients.view', 'reception.access', 'reports.', 'staff.view', 'staff.create', 'staff.update', 'staff.assign-department', 'staff.manage-employment', 'staff.manage-education', 'staff.manage-license', 'staff.manage-documents', 'staff.manage-emergency-contacts', 'staff.export'],
            'receptionist' => ['dashboard.', 'patients.view', 'patients.create', 'patients.update', 'patients.manage-payers', 'patients.manage-documents', 'patients.print-card', 'reception.', 'invoices.view', 'invoices.create', 'billing.access', 'observation.access', 'observation.view-bed-board', 'dental.access', 'dental.view-queue'],
            'cashier' => ['dashboard.', 'patients.view', 'invoices.view', 'billing.access', 'billing.create-invoice', 'billing.receive-payment', 'observation.view-billing', 'dental.view-billing'],
            'accountant' => ['dashboard.', 'accounting.', 'billing.', 'reports.view', 'reports.view-financial', 'insurance.view-nhif-report'],
            'doctor' => ['dashboard.', 'patients.view', 'opd.', 'laboratory-results.view', 'laboratory-results.release', 'laboratory-results.print', 'pharmacy.view-prescription', 'pharmacy.view-stock', 'bed-rest.admit', 'bed-rest.discharge', 'bed-rest.refer', 'observation.access', 'observation.view-', 'observation.admit', 'observation.create-order', 'observation.clinical-review', 'observation.discharge', 'observation.refer', 'observation.print-'],
            'clinical-officer' => ['dashboard.', 'patients.view', 'opd.', 'laboratory-results.view', 'laboratory-results.release', 'laboratory-results.print', 'pharmacy.view-prescription', 'pharmacy.view-stock', 'observation.access', 'observation.view-', 'observation.admit', 'observation.create-order', 'observation.clinical-review', 'observation.discharge'],
            'nurse' => ['dashboard.', 'patients.view', 'triage.', 'bed-rest.record-nursing-care', 'bed-rest.administer-medication', 'observation.access', 'observation.view-bed-board', 'observation.view-admission', 'observation.record-nursing-observation', 'observation.administer-medication', 'observation.record-iv-fluid', 'observation.record-oxygen', 'observation.record-nebulization', 'observation.record-intake-output', 'observation.manage-nursing-tasks', 'observation.create-handover', 'observation.acknowledge-handover', 'observation.manage-bed-cleaning'],
            'laboratory-technician' => ['dashboard.', 'laboratory.', 'laboratory-results.', 'laboratory-critical-results.', 'laboratory-outsourced-tests.', 'laboratory-reports.'],
            'pharmacist' => ['dashboard.', 'pharmacy.'],
            'dentist' => ['dashboard.', 'patients.view', 'dental.', 'prescriptions.view', 'prescriptions.create', 'appointments.create', 'referrals.create'],
            'rch-nurse' => ['dashboard.', 'rch.'],
            'store-keeper' => ['dashboard.', 'pharmacy.view-stock', 'pharmacy.receive-stock', 'pharmacy.transfer-stock', 'pharmacy.stock-count', 'pharmacy.adjust-stock', 'pharmacy.view-stock-card'],
            'records-officer' => ['dashboard.', 'patients.view', 'reports.view', 'staff.view', 'staff.manage-documents', 'staff.view-login-history'],
        ];
    }
}
