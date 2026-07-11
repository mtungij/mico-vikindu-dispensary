<?php

namespace Tests\Feature\Staff;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Livewire\Reports\Staff as StaffReport;
use App\Livewire\Staff\Index as StaffIndex;
use App\Livewire\Staff\Show as StaffShow;
use App\Models\Department;
use App\Models\Facility;
use App\Models\JobTitle;
use App\Models\Role;
use App\Models\StaffDocument;
use App\Models\StaffProfile;
use App\Models\StaffSignature;
use App\Models\User;
use App\Models\UserLoginHistory;
use App\Services\LicenseStatusService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\JobTitleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_staff(): void
    {
        $this->get(route('staff.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_staff_view_cannot_access_staff(): void
    {
        $this->bootstrappedFacility();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('staff.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_staff_with_generated_number_roles_and_department(): void
    {
        $admin = $this->bootstrappedFacility();
        $department = Department::query()->firstOrFail();
        $jobTitle = JobTitle::query()->firstOrFail();
        $role = Role::query()->where('name', 'receptionist')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(StaffIndex::class)
            ->call('create')
            ->set('personal.first_name', 'Asha')
            ->set('personal.last_name', 'Mosha')
            ->set('personal.primary_phone', '0712345678')
            ->set('employment.job_title_id', $jobTitle->id)
            ->set('employment.primary_department_id', $department->id)
            ->set('employment.employment_status', 'active')
            ->set('account.email', 'asha@example.test')
            ->set('account.temporary_password', 'password123')
            ->set('account.temporary_password_confirmation', 'password123')
            ->set('account.role_ids', [$role->id])
            ->call('save')
            ->assertHasNoErrors();

        $profile = StaffProfile::query()->where('first_name', 'Asha')->firstOrFail();
        $this->assertStringStartsWith('STF-', $profile->employee_number);
        $this->assertSame('+255712345678', $profile->primary_phone);
        $this->assertSame($department->id, $profile->user->primary_department_id);
        $this->assertTrue($profile->user->hasRole('receptionist'));
    }

    public function test_staff_profile_page_loads(): void
    {
        $admin = $this->bootstrappedFacility();
        $profile = $this->createStaff($admin);

        Livewire::actingAs($admin)
            ->test(StaffShow::class, ['staffProfile' => $profile])
            ->assertSee($profile->fullName());
    }

    public function test_license_status_service_marks_expired_and_expiring(): void
    {
        $service = app(LicenseStatusService::class);

        $this->assertSame('expired', $service->calculate(now()->subDay()->toDateString())->value);
        $this->assertSame('expiring', $service->calculate(now()->addDays(10)->toDateString())->value);
        $this->assertSame('active', $service->calculate(now()->addDays(45)->toDateString())->value);
    }

    public function test_staff_document_upload_and_secure_download_authorization(): void
    {
        Storage::fake('local');
        $admin = $this->bootstrappedFacility();
        $profile = $this->createStaff($admin);

        Livewire::actingAs($admin)
            ->test(StaffShow::class, ['staffProfile' => $profile])
            ->call('openDocumentModal')
            ->set('documentForm.document_type', 'nida')
            ->set('documentForm.document_name', 'NIDA Copy')
            ->set('documentFile', UploadedFile::fake()->create('nida.pdf', 100, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasNoErrors();

        $document = StaffDocument::query()->firstOrFail();
        Storage::disk('local')->assertExists($document->file_path);

        $this->actingAs($admin)->get(route('staff.documents.download', [$profile, $document]))->assertOk();

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('staff.documents.download', [$profile, $document]))->assertForbidden();
    }

    public function test_staff_signature_can_be_uploaded_replaced_previewed_and_deleted(): void
    {
        Storage::fake('local');
        $admin = $this->bootstrappedFacility();
        $profile = $this->createStaff($admin);

        Livewire::actingAs($admin)
            ->test(StaffShow::class, ['staffProfile' => $profile, 'tab' => 'signature'])
            ->assertSet('tab', 'signature')
            ->call('openSignatureModal')
            ->set('signatureFile', UploadedFile::fake()->image('signature.png')->size(100))
            ->call('uploadSignature')
            ->assertHasNoErrors();

        $first = StaffSignature::query()->firstOrFail();
        $this->assertTrue($first->is_active);
        Storage::disk('local')->assertExists($first->signature_path);
        $this->assertDatabaseHas('activity_logs', ['event' => 'signature_uploaded', 'subject_id' => $profile->id]);

        Livewire::actingAs($admin)
            ->test(StaffShow::class, ['staffProfile' => $profile, 'tab' => 'signature'])
            ->set('signatureFile', UploadedFile::fake()->image('signature-new.jpg')->size(100))
            ->call('uploadSignature')
            ->assertHasNoErrors();

        $this->assertFalse($first->refresh()->is_active);
        $this->assertSame(1, StaffSignature::query()->where('staff_id', $profile->id)->where('is_active', true)->count());
        $this->assertDatabaseHas('activity_logs', ['event' => 'signature_replaced', 'subject_id' => $profile->id]);

        $active = StaffSignature::query()->where('is_active', true)->firstOrFail();
        $this->actingAs($admin)->get(route('staff.signatures.view', [$profile, $active]))->assertOk();

        Livewire::actingAs($admin)
            ->test(StaffShow::class, ['staffProfile' => $profile, 'tab' => 'signature'])
            ->call('deleteSignature')
            ->assertHasNoErrors();

        $this->assertSame(0, StaffSignature::query()->where('staff_id', $profile->id)->where('is_active', true)->count());
        $this->assertSoftDeleted('staff_signatures', ['id' => $active->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'signature_deleted', 'subject_id' => $profile->id]);
    }

    public function test_login_history_is_recorded(): void
    {
        $this->bootstrappedFacility();
        $user = User::factory()->create([
            'email' => 'login@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->post(route('login'), [
            'email' => 'login@example.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('user_login_histories', [
            'user_id' => $user->id,
            'status' => 'successful',
        ]);
    }

    public function test_staff_report_csv_export_works(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->createStaff($admin);

        $this->actingAs($admin)
            ->get(route('reports.staff.export'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_license_refresh_command_runs(): void
    {
        $admin = $this->bootstrappedFacility();
        $profile = $this->createStaff($admin);
        $profile->professionalLicenses()->create([
            'license_type' => 'Practice',
            'professional_body' => 'Medical Council of Tanganyika',
            'registration_number' => 'REG-1',
            'expiry_date' => now()->subDay(),
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $this->artisan('staff:refresh-license-statuses')->assertSuccessful();
        $this->assertSame('expired', $profile->professionalLicenses()->first()->refresh()->status->value);
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'admin@dispensary.test']);
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

    private function createStaff(User $actor): StaffProfile
    {
        $department = Department::query()->firstOrFail();
        $jobTitle = JobTitle::query()->firstOrFail();
        $role = Role::query()->where('name', 'receptionist')->firstOrFail();

        return app(\App\Services\StaffService::class)->createStaff([
            'personal' => [
                'first_name' => 'Test',
                'middle_name' => null,
                'last_name' => 'Staff',
                'gender' => null,
                'date_of_birth' => null,
                'marital_status' => null,
                'nationality' => 'Tanzanian',
                'nida_number' => null,
                'passport_number' => null,
                'primary_phone' => '0712345678',
                'secondary_phone' => null,
                'personal_email' => null,
                'physical_address' => null,
                'postal_address' => null,
                'region' => null,
                'district' => null,
                'ward' => null,
                'street_or_village' => null,
                'biography' => null,
                'emergency_notes' => null,
            ],
            'employment' => [
                'job_title_id' => $jobTitle->id,
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
                'email' => fake()->unique()->safeEmail(),
                'phone' => null,
                'temporary_password' => 'password123',
                'temporary_password_confirmation' => 'password123',
                'status' => 'active',
                'must_change_password' => true,
                'role_ids' => [$role->id],
                'direct_permissions' => [],
            ],
            'departments' => [],
            'education' => [],
            'licenses' => [],
            'emergency_contacts' => [],
        ], $actor)['staff'];
    }
}
