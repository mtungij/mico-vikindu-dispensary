<?php

namespace Tests\Feature\Dental;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Livewire\Dental\Settings\Anaesthetics;
use App\Livewire\Dental\Settings\AppointmentTypes;
use App\Livewire\Dental\Settings\Chairs;
use App\Livewire\Dental\Settings\Consents;
use App\Livewire\Dental\Settings\Preferences;
use App\Livewire\Dental\Settings\ProcedureTemplates;
use App\Livewire\Dental\Settings\ProcedureTypes;
use App\Livewire\Dental\Settings\Rooms;
use App\Models\DentalAnaestheticType;
use App\Models\DentalAppointmentType;
use App\Models\DentalChair;
use App\Models\DentalConsentTemplate;
use App\Models\DentalProcedureTemplate;
use App\Models\DentalProcedureType;
use App\Models\DentalRoom;
use App\Models\Facility;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\DentalAnaestheticSeeder;
use Database\Seeders\DentalAppointmentTypeSeeder;
use Database\Seeders\DentalChairSeeder;
use Database\Seeders\DentalConsentTemplateSeeder;
use Database\Seeders\DentalProcedureTemplateSeeder;
use Database\Seeders\DentalProcedureTypeSeeder;
use Database\Seeders\DentalRoomSeeder;
use Database\Seeders\DentalServiceSeeder;
use Database\Seeders\DentalSettingsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DentalSettingsCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_unauthorized_user_cannot_access_dental_setup(): void
    {
        $this->get(route('settings.dental.procedure-types'))->assertRedirect(route('login'));
        $this->bootstrappedFacility();
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('settings.dental.procedure-types'))->assertForbidden();
    }

    public function test_authorized_user_can_render_all_new_dental_settings_pages(): void
    {
        $admin = $this->bootstrappedFacility();
        foreach ([ProcedureTypes::class, ProcedureTemplates::class, Anaesthetics::class, Consents::class, AppointmentTypes::class, Rooms::class, Chairs::class, Preferences::class] as $component) {
            Livewire::actingAs($admin)->test($component)->assertOk();
        }
        foreach (['settings.dental.procedure-types','settings.dental.procedure-templates','settings.dental.anaesthetics','settings.dental.consents','settings.dental.appointment-types','settings.dental.rooms','settings.dental.chairs','settings.dental.report-settings'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_dental_seeders_are_idempotent_and_create_foundation_records(): void
    {
        $this->bootstrappedFacility();
        $this->seed([DentalProcedureTypeSeeder::class, DentalAnaestheticSeeder::class, DentalConsentTemplateSeeder::class, DentalAppointmentTypeSeeder::class, DentalRoomSeeder::class, DentalChairSeeder::class, DentalProcedureTemplateSeeder::class, DentalSettingsSeeder::class]);
        $this->seed([DentalProcedureTypeSeeder::class, DentalAnaestheticSeeder::class, DentalConsentTemplateSeeder::class, DentalAppointmentTypeSeeder::class, DentalRoomSeeder::class, DentalChairSeeder::class, DentalProcedureTemplateSeeder::class, DentalSettingsSeeder::class]);

        $this->assertSame(1, DentalProcedureType::query()->where('code', 'SIMPLE_EXTRACTION')->count());
        $this->assertSame(1, DentalAnaestheticType::query()->where('code', 'LIDOCAINE_2')->count());
        $this->assertSame(1, DentalConsentTemplate::query()->where('code', 'EXTRACTION')->count());
        $this->assertSame(1, DentalAppointmentType::query()->where('code', 'DENTAL_CONSULTATION')->count());
        $this->assertGreaterThan(0, DentalRoom::query()->count());
        $this->assertGreaterThan(0, DentalChair::query()->count());
        $this->assertGreaterThan(0, DentalProcedureTemplate::query()->count());
    }

    public function test_procedure_type_modal_creates_facility_scoped_record_and_prevents_duplicate_code(): void
    {
        $admin = $this->bootstrappedFacility();

        Livewire::actingAs($admin)
            ->test(ProcedureTypes::class)
            ->call('create')
            ->set('form.name', 'Custom Preventive')
            ->set('form.code', 'CUSTOM_PREVENTIVE')
            ->set('form.category', 'preventive')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dental_procedure_types', ['facility_id' => currentFacility()->id, 'code' => 'CUSTOM_PREVENTIVE']);

        Livewire::actingAs($admin)
            ->test(ProcedureTypes::class)
            ->call('create')
            ->set('form.name', 'Duplicate')
            ->set('form.code', 'CUSTOM_PREVENTIVE')
            ->set('form.category', 'preventive')
            ->call('save')
            ->assertHasErrors(['form.code']);
    }

    public function test_facility_scopes_hide_other_facility_setup_records(): void
    {
        $admin = $this->bootstrappedFacility();
        $other = Facility::query()->create(['name'=>'Other Facility','code'=>'OTH','facility_type'=>FacilityType::Dispensary,'ownership_type'=>OwnershipType::Private,'phone_primary'=>'+255700000111','region'=>'Dar es Salaam','district'=>'Ilala','ward'=>'Upanga','physical_address'=>'Upanga','setup_completed_at'=>now(),'created_by'=>$admin->id,'updated_by'=>$admin->id]);
        DentalRoom::query()->create(['facility_id'=>$other->id,'name'=>'Other Dental Room','code'=>'OTHER_ROOM','is_active'=>true]);

        $this->assertFalse(DentalRoom::query()->forCurrentFacility()->where('code', 'OTHER_ROOM')->exists());
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        Facility::query()->create(['name'=>'Vikindu Dispensary','code'=>'VDP','facility_type'=>FacilityType::Dispensary,'ownership_type'=>OwnershipType::Private,'phone_primary'=>'+255700000000','region'=>'Dar es Salaam','district'=>'Temeke','ward'=>'Vikindu','physical_address'=>'Vikindu','setup_completed_at'=>now(),'created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, ServiceCategorySeeder::class, ServiceSeeder::class, DentalProcedureTypeSeeder::class, DentalServiceSeeder::class, DentalAnaestheticSeeder::class, DentalConsentTemplateSeeder::class, DentalAppointmentTypeSeeder::class, DentalRoomSeeder::class, DentalChairSeeder::class, DentalProcedureTemplateSeeder::class, DentalSettingsSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }
        return $admin;
    }
}
