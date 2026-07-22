<?php

namespace Tests\Feature;

use App\Enums\DepartmentType;
use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Livewire\Departments\Index as DepartmentsIndex;
use App\Livewire\JobTitles\Index as JobTitlesIndex;
use App\Livewire\Roles\ManagePermissions;
use App\Models\Department;
use App\Models\Facility;
use App\Models\JobTitle;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\JobTitleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Step3RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_department_from_livewire_page(): void
    {
        $admin = $this->bootstrappedFacility();

        Livewire::actingAs($admin)
            ->test(DepartmentsIndex::class)
            ->call('create')
            ->set('form.name', 'Emergency')
            ->set('form.code', 'emg')
            ->set('form.department_type', DepartmentType::Clinical->value)
            ->set('form.queue_enabled', true)
            ->set('form.clinical_department', true)
            ->set('form.can_receive_patients', true)
            ->set('form.requires_consultation', true)
            ->set('form.requires_triage', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('departments', [
            'name' => 'Emergency',
            'code' => 'EMG',
            'queue_enabled' => true,
            'clinical_department' => true,
            'can_receive_patients' => true,
            'requires_consultation' => true,
            'requires_triage' => true,
        ]);
    }

    public function test_super_admin_can_create_job_title_for_department(): void
    {
        $admin = $this->bootstrappedFacility();
        $department = Department::query()->create([
            'facility_id' => Facility::query()->first()->id,
            'name' => 'Clinical',
            'code' => 'CLN',
            'department_type' => DepartmentType::Clinical,
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(JobTitlesIndex::class)
            ->call('create')
            ->set('form.department_id', $department->id)
            ->set('form.name', 'Medical Officer')
            ->set('form.code', 'mo')
            ->set('form.requires_professional_license', true)
            ->set('form.license_authority', 'Medical Council')
            ->set('form.is_clinical', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('job_titles', [
            'department_id' => $department->id,
            'name' => 'Medical Officer',
            'code' => 'MO',
            'requires_professional_license' => true,
        ]);
    }

    public function test_role_permissions_page_syncs_selected_permissions(): void
    {
        $admin = $this->bootstrappedFacility();
        $role = Role::query()->where('name', 'administrator')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ManagePermissions::class, ['role' => $role])
            ->set('selectedPermissions', ['departments.view', 'departments.create'])
            ->call('save')
            ->assertHasNoErrors();

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('departments.view'));
        $this->assertTrue($role->hasPermissionTo('departments.create'));
    }

    public function test_lab_order_create_permission_is_preselected_only_for_default_clinician_roles(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->seed(RolePermissionSeeder::class);

        foreach (['doctor', 'clinical-officer'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            $this->assertTrue($role->hasPermissionTo('laboratory-orders.create'));
            $this->assertSame(1, $role->permissions()->where('permissions.name', 'laboratory-orders.create')->count());

            Livewire::actingAs($admin)
                ->test(ManagePermissions::class, ['role' => $role])
                ->assertSet(
                    'selectedPermissions',
                    fn (array $permissions): bool => in_array('laboratory-orders.create', $permissions, true),
                );
        }

        foreach (['receptionist', 'cashier', 'laboratory-technician', 'pharmacist'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            $this->assertFalse($role->hasPermissionTo('laboratory-orders.create'));
        }
    }

    public function test_user_without_department_permission_cannot_access_department_settings(): void
    {
        $this->bootstrappedFacility();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.departments.index'))
            ->assertForbidden();
    }

    public function test_step_three_seeders_are_idempotent(): void
    {
        $this->bootstrappedFacility();

        $firstCounts = [
            'permissions' => Permission::query()->count(),
            'roles' => Role::query()->count(),
            'departments' => Department::query()->count(),
            'job_titles' => JobTitle::query()->count(),
        ];

        $this->seed([PermissionSeeder::class, RoleSeeder::class, DepartmentSeeder::class, JobTitleSeeder::class, RolePermissionSeeder::class]);
        $this->seed([PermissionSeeder::class, RoleSeeder::class, DepartmentSeeder::class, JobTitleSeeder::class, RolePermissionSeeder::class]);

        $this->assertSame($firstCounts['permissions'], Permission::query()->count());
        $this->assertSame($firstCounts['roles'], Role::query()->count());
        $this->assertSame($firstCounts['departments'], Department::query()->count());
        $this->assertSame($firstCounts['job_titles'], JobTitle::query()->count());
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create([
            'email' => 'admin@dispensary.test',
        ]);

        Facility::query()->create([
            'name' => 'James Medical Dispensary',
            'code' => 'JMD',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000000',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'ward' => 'Kijitonyama',
            'physical_address' => 'Kijitonyama',
            'setup_completed_at' => now(),
            'setup_current_step' => 6,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->seed([PermissionSeeder::class, RoleSeeder::class, DepartmentSeeder::class, JobTitleSeeder::class, RolePermissionSeeder::class]);

        return $admin;
    }
}
