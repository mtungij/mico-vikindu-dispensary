<?php

namespace Tests\Feature\Rch;

use App\Enums\FacilityType;
use App\Enums\Gender;
use App\Enums\OwnershipType;
use App\Models\ClinicalAlert;
use App\Models\Facility;
use App\Models\ImmunizationSchedule;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Pregnancy;
use App\Models\PregnancyRiskFactorType;
use App\Models\RchChild;
use App\Models\User;
use App\Models\Vaccine;
use App\Services\ChildGrowthAssessmentService;
use App\Services\FamilyPlanningService;
use App\Services\ImmunizationAdministrationService;
use App\Services\PregnancyDatingService;
use App\Services\PregnancyRiskAssessmentService;
use App\Services\PregnancyService;
use App\Services\RchChildService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\FamilyPlanningMethodSeeder;
use Database\Seeders\ImmunizationScheduleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PregnancyRiskFactorSeeder;
use Database\Seeders\RchServiceSeeder;
use Database\Seeders\VaccineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_rch_routes_render_for_authorized_user(): void
    {
        [$admin, $mother, $child] = $this->context();
        $pregnancy = app(PregnancyService::class)->register($mother, ['lmp_date' => now()->subWeeks(16)->toDateString()], $admin);
        $rchChild = app(RchChildService::class)->register($child, ['birth_date' => now()->subMonths(3)->toDateString(), 'sex_at_birth' => 'female', 'mother_patient_id' => $mother->id], $admin);
        $client = app(FamilyPlanningService::class)->register($mother, ['registration_date' => today()->toDateString(), 'client_type' => 'new'], $admin);

        foreach ([
            route('rch.dashboard'),
            route('rch.index'),
            route('rch.pregnancies.index'),
            route('rch.pregnancies.register'),
            route('rch.pregnancies.show', $pregnancy),
            route('rch.pregnancies.anc-visit', $pregnancy),
            route('rch.pregnancies.risk', $pregnancy),
            route('rch.family-planning.index'),
            route('rch.family-planning.show', $client),
            route('rch.family-planning.visit', $client),
            route('rch.children.index'),
            route('rch.children.register'),
            route('rch.children.show', $rchChild),
            route('rch.children.growth', $rchChild),
            route('rch.children.growth-measurement', $rchChild),
            route('rch.immunization.index'),
            route('rch.immunization.defaulters'),
            route('rch.immunization.schedules'),
            route('rch.immunization.administer', $rchChild),
            route('rch.children.immunization-card', $rchChild),
            route('rch.reports'),
            route('rch.settings.risk-factors'),
            route('rch.settings.family-planning-methods'),
            route('rch.settings.vaccines'),
            route('rch.settings.immunization-schedules'),
            route('rch.settings.growth-standards'),
            route('rch.settings.preferences'),
            route('rch.settings.report-settings'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_pregnancy_registration_blocks_duplicate_and_calculates_edd(): void
    {
        [$admin, $mother] = $this->context();
        $lmp = now()->subWeeks(12)->toDateString();
        $pregnancy = app(PregnancyService::class)->register($mother, ['lmp_date' => $lmp, 'booking_weight_kg' => 60, 'booking_height_cm' => 160], $admin);

        $this->assertSame(now()->subWeeks(12)->addDays(280)->toDateString(), $pregnancy->estimated_delivery_date->toDateString());
        $this->assertSame(23.44, (float) $pregnancy->booking_bmi);
        $this->assertDatabaseHas('pregnancy_dating_records', ['pregnancy_id' => $pregnancy->id, 'is_primary' => true]);
        $this->expectException(ValidationException::class);
        app(PregnancyService::class)->register($mother, ['lmp_date' => $lmp], $admin);
    }

    public function test_risk_assessment_creates_high_risk_alert(): void
    {
        [$admin, $mother] = $this->context();
        $pregnancy = app(PregnancyService::class)->register($mother, ['lmp_date' => now()->subWeeks(20)->toDateString()], $admin);
        app(PregnancyRiskAssessmentService::class)->assessPregnancy($pregnancy, null, ['codes' => ['hypertension']], $admin);

        $this->assertTrue($pregnancy->refresh()->high_risk);
        $this->assertDatabaseHas('clinical_alerts', ['patient_id' => $mother->id, 'alert_type' => 'pregnancy_risk']);
    }

    public function test_child_registration_growth_and_relationship_workflow(): void
    {
        [$admin, $mother, $child] = $this->context();
        $rchChild = app(RchChildService::class)->register($child, ['birth_date' => now()->subMonths(6)->toDateString(), 'sex_at_birth' => 'female', 'mother_patient_id' => $mother->id], $admin);
        $growth = app(ChildGrowthAssessmentService::class);

        $this->assertDatabaseHas('patient_relationships', ['patient_id' => $child->id, 'related_patient_id' => $mother->id, 'relationship_type' => 'mother']);
        $this->assertGreaterThan(170, $growth->calculateAgeInDays($rchChild));
        $this->assertSame(13.84, $growth->calculateBmi(5, 60.1));
    }

    public function test_FamilyPlanning_method_history_is_preserved(): void
    {
        [$admin, $mother] = $this->context();
        $method = \App\Models\FamilyPlanningMethod::query()->where('code', 'INJ')->firstOrFail();
        $client = app(FamilyPlanningService::class)->register($mother, ['registration_date' => today()->toDateString(), 'client_type' => 'new'], $admin);
        app(FamilyPlanningService::class)->recordVisit($client, ['visit_date' => today()->toDateString(), 'visit_type' => 'initiation', 'selected_method_id' => $method->id], $admin);

        $this->assertDatabaseHas('family_planning_method_episodes', ['family_planning_client_id' => $client->id, 'method_id' => $method->id, 'status' => 'active']);
        $this->assertSame($method->id, $client->refresh()->current_method_id);
    }

    public function test_immunization_administration_blocks_duplicate_dose(): void
    {
        [$admin, $mother, $child] = $this->context();
        $rchChild = app(RchChildService::class)->register($child, ['birth_date' => now()->subYear()->toDateString(), 'sex_at_birth' => 'female', 'mother_patient_id' => $mother->id], $admin);
        $schedule = ImmunizationSchedule::query()->with('items.vaccine')->firstOrFail();
        $item = $schedule->items->first();
        app(ImmunizationAdministrationService::class)->administer($rchChild, $item->vaccine, ['immunization_schedule_item_id' => $item->id, 'vaccine_id' => $item->vaccine_id, 'administration_date' => today()->toDateString()], $admin);

        $this->assertDatabaseHas('immunization_administrations', ['rch_child_id' => $rchChild->id, 'vaccine_id' => $item->vaccine_id, 'status' => 'administered']);
        $this->expectException(ValidationException::class);
        app(ImmunizationAdministrationService::class)->administer($rchChild, $item->vaccine, ['immunization_schedule_item_id' => $item->id, 'vaccine_id' => $item->vaccine_id, 'administration_date' => today()->toDateString()], $admin);
    }

    public function test_unauthorized_user_cannot_see_rch_sidebar(): void
    {
        [, , , $facility] = $this->context(false);
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('RCH Dashboard')->assertDontSee('RCH Queue');
    }

    private function context(bool $authorize = true): array
    {
        $admin = User::factory()->superAdmin()->create();
        $facility = Facility::query()->create(['name'=>'Vikindu Dispensary','code'=>'VDP','facility_type'=>FacilityType::Dispensary,'ownership_type'=>OwnershipType::Private,'phone_primary'=>'+255700000000','region'=>'Dar es Salaam','district'=>'Temeke','ward'=>'Vikindu','physical_address'=>'Vikindu','setup_completed_at'=>now(),'created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, RchServiceSeeder::class, PregnancyRiskFactorSeeder::class, FamilyPlanningMethodSeeder::class, VaccineSeeder::class, ImmunizationScheduleSeeder::class]);
        if ($authorize) {
            foreach (Permission::query()->pluck('name') as $permission) {
                $admin->givePermissionTo($permission);
            }
        }
        $mother = Patient::factory()->create(['facility_id'=>$facility->id,'gender'=>Gender::Female,'date_of_birth'=>now()->subYears(25),'created_by'=>$admin->id]);
        $child = Patient::factory()->create(['facility_id'=>$facility->id,'gender'=>Gender::Female,'date_of_birth'=>now()->subMonths(6),'created_by'=>$admin->id]);

        return [$admin, $mother, $child, $facility];
    }
}
