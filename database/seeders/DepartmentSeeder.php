<?php

namespace Database\Seeders;

use App\Enums\DepartmentType;
use App\Models\Department;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        $admin = User::query()->where('is_super_admin', true)->first();

        if (! $facility) {
            return;
        }

        foreach ($this->departments() as $index => $department) {
            Department::query()->updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $department['code']],
                array_merge($department, [
                    'facility_id' => $facility->id,
                    'sort_order' => $index + 1,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ]),
            );
        }
    }

    private function departments(): array
    {
        return [
            ['name' => 'Reception', 'code' => 'REC', 'department_type' => DepartmentType::Administrative, 'icon' => 'clipboard-plus', 'queue_enabled' => true, 'can_receive_patients' => false],
            ['name' => 'Triage', 'code' => 'TRI', 'department_type' => DepartmentType::Clinical, 'icon' => 'heart-pulse', 'queue_enabled' => true, 'clinical_department' => true, 'can_receive_patients' => false],
            ['name' => 'OPD', 'code' => 'OPD', 'department_type' => DepartmentType::Clinical, 'icon' => 'stethoscope', 'queue_enabled' => true, 'billing_enabled' => true, 'clinical_department' => true, 'can_receive_patients' => true, 'requires_consultation' => true, 'requires_triage' => true],
            ['name' => 'Laboratory', 'code' => 'LAB', 'department_type' => DepartmentType::Diagnostic, 'icon' => 'flask-conical', 'queue_enabled' => true, 'billing_enabled' => true, 'clinical_department' => true, 'stock_location_enabled' => true, 'can_receive_patients' => true, 'requires_consultation' => false, 'requires_triage' => false],
            ['name' => 'Pharmacy', 'code' => 'PHA', 'department_type' => DepartmentType::Pharmacy, 'icon' => 'pill', 'queue_enabled' => true, 'billing_enabled' => true, 'stock_location_enabled' => true, 'can_receive_patients' => true, 'requires_consultation' => false, 'requires_triage' => false],
            ['name' => 'Dental', 'code' => 'DEN', 'department_type' => DepartmentType::Dental, 'icon' => 'badge-plus', 'queue_enabled' => true, 'billing_enabled' => true, 'clinical_department' => true, 'can_receive_patients' => true, 'requires_consultation' => true, 'requires_triage' => true],
            ['name' => 'RCH', 'code' => 'RCH', 'department_type' => DepartmentType::MaternalChildHealth, 'icon' => 'baby', 'queue_enabled' => true, 'billing_enabled' => true, 'clinical_department' => true, 'can_receive_patients' => true, 'requires_consultation' => true, 'requires_triage' => true],
            ['name' => 'Bed Rest / Observation', 'code' => 'BED', 'department_type' => DepartmentType::Observation, 'icon' => 'bed', 'billing_enabled' => true, 'clinical_department' => true, 'stock_location_enabled' => true, 'can_receive_patients' => true, 'requires_consultation' => true, 'requires_triage' => true],
            ['name' => 'Billing', 'code' => 'BIL', 'department_type' => DepartmentType::Financial, 'icon' => 'receipt', 'queue_enabled' => true, 'billing_enabled' => true, 'can_receive_patients' => false],
            ['name' => 'Accounting', 'code' => 'ACC', 'department_type' => DepartmentType::Financial, 'icon' => 'wallet-cards', 'can_receive_patients' => false],
            ['name' => 'Store', 'code' => 'STR', 'department_type' => DepartmentType::Support, 'icon' => 'boxes', 'stock_location_enabled' => true, 'can_receive_patients' => false],
            ['name' => 'Administration', 'code' => 'ADM', 'department_type' => DepartmentType::Administrative, 'icon' => 'building-2', 'can_receive_patients' => false],
        ];
    }
}
