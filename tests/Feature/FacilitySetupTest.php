<?php

namespace Tests\Feature;

use App\Enums\FacilityDocumentType;
use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Events\FacilitySetupCompleted;
use App\Livewire\Facility\SetupWizard;
use App\Livewire\Settings\Facility\Index as FacilitySettingsIndex;
use App\Models\Facility;
use App\Models\FacilityDocument;
use App\Models\User;
use App\Services\FacilityContext;
use App\Services\PhoneNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FacilitySetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_facility_setup(): void
    {
        $this->get(route('facility.setup'))->assertRedirect(route('login'));
    }

    public function test_active_super_admin_can_access_setup(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('facility.setup'))
            ->assertOk()
            ->assertSee('Facility Setup');
    }

    public function test_non_super_admin_cannot_complete_setup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('facility.setup'))->assertForbidden();
    }

    public function test_user_is_redirected_from_dashboard_when_setup_is_incomplete(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertRedirect(route('facility.setup'));
    }

    public function test_dashboard_is_accessible_after_setup_completion(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->completedFacility($admin);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    }

    public function test_step_one_validates_required_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->call('saveDraft')
            ->assertHasErrors(['name', 'facility_type', 'ownership_type', 'phone_primary']);
    }

    public function test_tanzania_phone_number_is_normalized_correctly(): void
    {
        $this->assertSame('+255712345678', app(PhoneNumberService::class)->normalize('0712345678'));
        $this->assertSame('+255712345678', app(PhoneNumberService::class)->normalize('255712345678'));
        $this->assertSame('+255712345678', app(PhoneNumberService::class)->normalize('+255712345678'));
    }

    public function test_invalid_region_district_combination_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->set('step', 2)
            ->set('country', 'Tanzania')
            ->set('region', 'Dar es Salaam')
            ->set('district', 'Moshi Municipal')
            ->set('ward', 'Kijitonyama')
            ->set('physical_address', 'Kijitonyama')
            ->call('saveDraft')
            ->assertHasErrors(['district']);
    }

    public function test_nhif_number_is_required_when_nhif_is_enabled(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->set('step', 3)
            ->set('accepts_insurance', true)
            ->set('nhif_enabled', true)
            ->call('saveDraft')
            ->assertHasErrors(['nhif_accreditation_number']);
    }

    public function test_draft_can_be_saved(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->set('name', 'James Medical Dispensary')
            ->set('facility_type', FacilityType::Dispensary->value)
            ->set('ownership_type', OwnershipType::Private->value)
            ->set('phone_primary', '0712345678')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('facilities', [
            'name' => 'James Medical Dispensary',
            'phone_primary' => '+255712345678',
        ]);
    }

    public function test_wizard_resumes_from_saved_step(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->draftFacility($admin, ['setup_current_step' => 3]);

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->assertSet('step', 3);
    }

    public function test_logo_upload_accepts_valid_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $this->draftFacility($admin);

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->set('step', 4)
            ->set('logo', UploadedFile::fake()->image('logo.png', 120, 120)->size(500))
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertNotNull(Facility::first()->logo_path);
    }

    public function test_invalid_executable_upload_is_rejected(): void
    {
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $this->draftFacility($admin);

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->set('document_name', 'Bad file')
            ->set('document_type', FacilityDocumentType::Other->value)
            ->set('document_file', UploadedFile::fake()->create('bad.php', 1, 'application/x-php'))
            ->call('uploadDocument')
            ->assertHasErrors(['document_file']);
    }

    public function test_large_file_upload_is_rejected(): void
    {
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $this->draftFacility($admin);

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->set('document_name', 'Large file')
            ->set('document_type', FacilityDocumentType::Other->value)
            ->set('document_file', UploadedFile::fake()->create('large.pdf', 6000, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasErrors(['document_file']);
    }

    public function test_facility_document_can_be_uploaded(): void
    {
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $this->draftFacility($admin);

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->set('document_name', 'Registration Certificate')
            ->set('document_type', FacilityDocumentType::RegistrationCertificate->value)
            ->set('document_file', UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('facility_documents', [
            'document_name' => 'Registration Certificate',
        ]);
    }

    public function test_unauthorized_user_cannot_view_secure_document(): void
    {
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $facility = $this->completedFacility($admin);
        $document = $this->document($facility, $admin);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.facility.documents.view', $document))
            ->assertForbidden();
    }

    public function test_facility_setup_completion_sets_setup_completed_at_and_saves_settings(): void
    {
        Event::fake([FacilitySetupCompleted::class]);
        $admin = User::factory()->superAdmin()->create();
        $this->draftFacility($admin);

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->set('step', 6)
            ->call('completeSetup')
            ->assertHasNoErrors();

        $facility = Facility::first();
        $this->assertNotNull($facility->setup_completed_at);
        $this->assertDatabaseHas('facility_settings', ['facility_id' => $facility->id, 'key' => 'enable_audit_logs']);
        Event::assertDispatched(FacilitySetupCompleted::class);
    }

    public function test_completion_does_not_complete_when_required_data_is_missing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Facility::query()->create([
            'name' => 'Incomplete',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000000',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'ward' => 'Kijitonyama',
            'created_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->set('step', 6)
            ->call('completeSetup')
            ->assertHasErrors(['completion']);

        $this->assertNull(Facility::first()->setup_completed_at);
    }

    public function test_facility_settings_page_loads_after_setup(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->completedFacility($admin);

        $this->actingAs($admin)->get(route('settings.facility'))
            ->assertOk()
            ->assertSee('Facility Settings');
    }

    public function test_facility_name_can_be_updated_by_super_admin_and_cache_refreshes(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->completedFacility($admin);
        $this->assertSame('James Medical Dispensary', app(FacilityContext::class)->current()?->name);

        Livewire::actingAs($admin)
            ->test(FacilitySettingsIndex::class)
            ->set('name', 'Updated Dispensary')
            ->set('phone_primary', '+255700000000')
            ->call('saveBasic')
            ->assertHasNoErrors();

        $this->assertSame('Updated Dispensary', app(FacilityContext::class)->current()?->name);
    }

    public function test_seeder_creates_super_admin_with_setup_access(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@dispensary.test',
            'is_super_admin' => true,
        ]);
    }

    public function test_dashboard_displays_facility_name_after_completion(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->completedFacility($admin);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('James Medical Dispensary');
    }

    private function draftFacility(User $user, array $overrides = []): Facility
    {
        return Facility::query()->create(array_merge([
            'name' => 'James Medical Dispensary',
            'code' => 'JMD',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000000',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'ward' => 'Kijitonyama',
            'physical_address' => 'Kijitonyama',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $overrides));
    }

    private function completedFacility(User $user): Facility
    {
        return $this->draftFacility($user, ['setup_completed_at' => now(), 'setup_current_step' => 6]);
    }

    private function document(Facility $facility, User $user): FacilityDocument
    {
        Storage::disk('local')->put('facilities/'.$facility->id.'/documents/test.pdf', 'PDF');

        return FacilityDocument::query()->create([
            'facility_id' => $facility->id,
            'document_type' => FacilityDocumentType::RegistrationCertificate,
            'document_name' => 'Certificate',
            'file_path' => 'facilities/'.$facility->id.'/documents/test.pdf',
            'uploaded_by' => $user->id,
        ]);
    }
}
