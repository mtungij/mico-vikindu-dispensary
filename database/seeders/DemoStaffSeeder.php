<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Facility;
use App\Models\JobTitle;
use App\Models\Role;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Database\Seeder;

class DemoStaffSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoStaffSeeder skipped outside local/testing environment.');
            return;
        }

        $facility = Facility::query()->first();
        $actor = User::query()->where('email', 'admin@dispensary.test')->first();
        if (! $facility || ! $actor) {
            return;
        }

        $roles = ['receptionist', 'clinical-officer', 'nurse', 'laboratory-technician', 'pharmacist', 'dentist', 'rch-nurse', 'cashier', 'accountant'];

        foreach ($roles as $index => $roleName) {
            if (User::query()->where('email', $roleName.'@dispensary.test')->exists()) {
                continue;
            }

            $role = Role::query()->where('name', $roleName)->first();
            $jobTitle = JobTitle::query()->where('facility_id', $facility->id)->skip($index)->first() ?? JobTitle::query()->where('facility_id', $facility->id)->first();
            $department = $jobTitle?->department ?? Department::query()->where('facility_id', $facility->id)->first();

            if (! $role || ! $department) {
                continue;
            }

            app(StaffService::class)->createStaff([
                'personal' => [
                    'first_name' => str($roleName)->replace('-', ' ')->title()->before(' ')->toString(),
                    'middle_name' => null,
                    'last_name' => 'Demo',
                    'gender' => null,
                    'date_of_birth' => null,
                    'marital_status' => null,
                    'nationality' => 'Tanzanian',
                    'nida_number' => null,
                    'passport_number' => null,
                    'primary_phone' => '0700'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
                    'secondary_phone' => null,
                    'personal_email' => null,
                    'physical_address' => null,
                    'postal_address' => null,
                    'region' => 'Dar es Salaam',
                    'district' => 'Kinondoni',
                    'ward' => null,
                    'street_or_village' => null,
                    'biography' => null,
                    'emergency_notes' => null,
                ],
                'employment' => [
                    'job_title_id' => $jobTitle?->id,
                    'primary_department_id' => $department->id,
                    'employment_category' => 'permanent',
                    'employment_status' => 'active',
                    'employment_start_date' => now()->toDateString(),
                    'probation_end_date' => null,
                    'contract_start_date' => null,
                    'contract_end_date' => null,
                    'termination_date' => null,
                    'termination_reason' => null,
                    'payroll_number' => null,
                    'supervisor_user_id' => null,
                    'work_location' => null,
                    'notes' => null,
                ],
                'account' => [
                    'create_login_account' => true,
                    'email' => $roleName.'@dispensary.test',
                    'phone' => null,
                    'temporary_password' => 'password',
                    'temporary_password_confirmation' => 'password',
                    'status' => 'active',
                    'must_change_password' => true,
                    'role_ids' => [$role->id],
                    'direct_permissions' => [],
                ],
                'departments' => [],
                'education' => [],
                'licenses' => [],
                'emergency_contacts' => [],
            ], $actor);
        }
    }
}
