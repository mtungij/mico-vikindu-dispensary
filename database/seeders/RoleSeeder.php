<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        $admin = User::query()->where('email', 'admin@dispensary.test')->first();

        foreach ($this->roles() as $slug => [$display, $system]) {
            Role::query()->updateOrCreate(
                ['name' => $slug, 'guard_name' => 'web'],
                [
                    'display_name' => $display,
                    'description' => $display,
                    'facility_id' => $slug === 'super-admin' ? null : $facility?->id,
                    'is_system' => $system,
                    'is_active' => true,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ],
            );
        }

        if ($admin) {
            $admin->assignRole('super-admin');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function roles(): array
    {
        return [
            'super-admin' => ['Super Admin', true],
            'administrator' => ['Administrator', true],
            'facility-manager' => ['Facility Manager', true],
            'receptionist' => ['Receptionist', true],
            'cashier' => ['Cashier', true],
            'accountant' => ['Accountant', true],
            'doctor' => ['Doctor', true],
            'clinical-officer' => ['Clinical Officer', true],
            'nurse' => ['Nurse', true],
            'laboratory-technician' => ['Laboratory Technician', true],
            'pharmacist' => ['Pharmacist', true],
            'dentist' => ['Dentist', true],
            'rch-nurse' => ['RCH Nurse', true],
            'store-keeper' => ['Store Keeper', true],
            'records-officer' => ['Records Officer', true],
        ];
    }
}
