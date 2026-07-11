<?php

namespace Tests\Feature\Dental;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Enums\VisitStatus;
use App\Livewire\Dental\Queue as DentalQueue;
use App\Models\Department;
use App\Models\DentalFindingType;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\User;
use App\Models\Visit;
use App\Services\DentalConsentService;
use App\Services\DentalDiagnosisService;
use App\Services\DentalEncounterService;
use App\Services\DentalEndodonticService;
use App\Services\DentalLabOrderService;
use App\Services\DentalOdontogramService;
use App\Services\DentalPeriodontalService;
use App\Services\DentalProcedureService;
use App\Services\DentalTreatmentPlanService;
use Database\Seeders\DentalFindingTypeSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Step10DentalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dental_queue(): void
    {
        $this->get(route('dental.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_render_dental_pages_settings_and_reports(): void
    {
        $admin = $this->bootstrappedFacility();

        Livewire::actingAs($admin)->test(DentalQueue::class)->assertOk();
        $this->actingAs($admin)->get(route('dental.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('settings.dental.findings'))->assertOk();
        $this->actingAs($admin)->get(route('settings.dental.services'))->assertOk();
        $this->actingAs($admin)->get(route('settings.dental.materials'))->assertOk();
        $this->actingAs($admin)->get(route('settings.dental.consents'))->assertOk();
        $this->actingAs($admin)->get(route('settings.dental.preferences'))->assertOk();
        $this->actingAs($admin)->get(route('reports.dental.procedures'))->assertOk();
        $this->actingAs($admin)->get(route('reports.dental.export', 'procedures'))->assertOk();
    }

    public function test_dental_encounter_starts_once_and_initializes_adult_odontogram(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);

        $encounter = app(DentalEncounterService::class)->start($visit, $admin);

        $this->assertStringStartsWith('DEN-', $encounter->dental_encounter_number);
        $this->assertSame(32, $encounter->toothRecords()->count());
        $this->assertSame(VisitStatus::InConsultation, $visit->refresh()->visit_status);
        $this->assertDatabaseHas('activity_logs', ['event' => 'dental_encounter_started', 'subject_id' => $encounter->id]);

        $otherProvider = User::factory()->create();
        $this->expectException(ValidationException::class);
        app(DentalEncounterService::class)->start($visit->refresh(), $otherProvider);
    }

    public function test_odontogram_findings_validate_surfaces_and_can_mark_errors(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = app(DentalEncounterService::class)->start($this->visit($admin), $admin);
        $caries = DentalFindingType::query()->where('code', 'CARIES')->firstOrFail();
        $missing = DentalFindingType::query()->where('code', 'MISSING')->firstOrFail();

        $finding = app(DentalOdontogramService::class)->addFinding($encounter, [
            'tooth_number' => '16',
            'surface' => 'occlusal',
            'finding_type_id' => $caries->id,
            'severity' => 'moderate',
        ], $admin);

        $this->assertSame('active', $finding->finding_status->value);
        $record = app(DentalOdontogramService::class)->markToothMissing($encounter, '18', $admin);
        $this->assertSame('missing', $record->tooth_status->value);

        app(DentalOdontogramService::class)->markFindingError($finding, 'Duplicate entry', $admin);
        $this->assertSame('entered_in_error', $finding->refresh()->finding_status->value);

        $this->expectException(ValidationException::class);
        app(DentalOdontogramService::class)->addFinding($encounter, [
            'tooth_number' => '17',
            'surface' => 'occlusal',
            'finding_type_id' => $missing->id,
        ], $admin);
    }

    public function test_periodontal_diagnosis_plan_procedure_billing_and_completion_workflow(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = app(DentalEncounterService::class)->start($this->visit($admin), $admin);
        $service = $this->dentalService('Extraction', 'DENT-EXT', $admin);

        $assessment = app(DentalPeriodontalService::class)->record($encounter, [
            'plaque_index' => 25,
            'bleeding_index' => 10,
            'oral_hygiene_status' => 'Fair',
        ], [['tooth_number' => '16', 'surface' => 'buccal', 'pocket_depth_mm' => 4]], $admin);
        $this->assertSame(1, $assessment->measurements()->count());

        app(DentalDiagnosisService::class)->add($encounter, [
            'tooth_number' => '16',
            'diagnosis_name' => 'Non-restorable tooth',
            'certainty' => 'confirmed',
            'is_primary' => true,
        ], $admin);

        $plan = app(DentalTreatmentPlanService::class)->createPlan($encounter, ['title' => 'Extraction plan'], $admin);
        $item = app(DentalTreatmentPlanService::class)->addItem($plan, $service, ['tooth_number' => '16', 'quantity' => 1], $admin);
        $this->assertSame('15000.00', $item->total_amount);

        app(DentalConsentService::class)->create($encounter, [
            'consent_type' => 'oral_surgery',
            'consent_text_snapshot' => 'Extraction consent',
            'patient_or_guardian_name' => 'Test Patient',
            'consent_given' => true,
        ], $admin);
        $procedure = app(DentalProcedureService::class)->createProcedure($encounter, $service, [
            'procedure_type' => 'oral_surgery',
            'tooth_number' => '16',
            'indication' => 'Pain',
        ], $admin);
        $completed = app(DentalProcedureService::class)->completeProcedure($procedure, $admin);

        $this->assertSame('completed', $completed->status->value);
        $this->assertNotNull($completed->invoice_item_id);
        $this->assertSame('extracted', $encounter->toothRecords()->where('tooth_number', '16')->firstOrFail()->tooth_status->value);

        app(DentalEncounterService::class)->saveDraft($encounter->refresh(), [
            'clinical_summary' => 'Extraction completed without complications.',
            'treatment_plan_summary' => 'Review after one week.',
        ], $admin);
        $completedEncounter = app(DentalEncounterService::class)->complete($encounter->refresh(), $admin);
        $this->assertSame('completed', $completedEncounter->status->value);
    }

    public function test_signed_consent_is_immutable_and_ortho_endo_lab_foundations_work(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = app(DentalEncounterService::class)->start($this->visit($admin), $admin);

        $consent = app(DentalConsentService::class)->create($encounter, [
            'consent_type' => 'endodontic',
            'consent_text_snapshot' => 'Endodontic consent',
            'patient_or_guardian_name' => 'Test Patient',
            'consent_given' => true,
        ], $admin);

        $case = app(\App\Services\OrthodonticCaseService::class)->create($encounter, [
            'chief_concern' => 'Crowding',
            'diagnosis' => 'Class I crowding',
        ], $admin);
        $endo = app(DentalEndodonticService::class)->create($encounter, [
            'tooth_number' => '11',
            'diagnosis' => 'Irreversible pulpitis',
        ], $admin);
        $lab = app(DentalLabOrderService::class)->create($encounter, [
            'work_type' => 'crown',
            'tooth_numbers' => ['11'],
            'shade' => 'A2',
        ], $admin);

        $this->assertStringStartsWith('ORT-', $case->case_number);
        $this->assertSame('11', $endo->tooth_number);
        $this->assertStringStartsWith('DL-', $lab->order_number);

        $this->expectException(ValidationException::class);
        app(DentalConsentService::class)->update($consent, ['patient_or_guardian_name' => 'Changed']);
    }

    public function test_print_routes_render_for_chart_treatment_plan_and_procedure(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = app(DentalEncounterService::class)->start($this->visit($admin), $admin);
        $service = $this->dentalService('Dental Filling', 'DENT-FILL', $admin);
        $plan = app(DentalTreatmentPlanService::class)->createPlan($encounter, ['title' => 'Filling plan'], $admin);
        app(DentalTreatmentPlanService::class)->addItem($plan, $service, ['tooth_number' => '36'], $admin);
        $procedure = app(DentalProcedureService::class)->createProcedure($encounter, $service, [
            'procedure_type' => 'restorative',
            'tooth_number' => '36',
            'surfaces' => ['occlusal'],
        ], $admin);

        $this->actingAs($admin)->get(route('dental.chart.print', $encounter))->assertOk();
        $this->actingAs($admin)->get(route('dental.treatment-plans.print', $plan))->assertOk();
        $this->actingAs($admin)->get(route('dental.procedures.print', $procedure))->assertOk();
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        Facility::query()->create([
            'name' => 'Vikindu Dispensary',
            'code' => 'VDP',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000000',
            'region' => 'Dar es Salaam',
            'district' => 'Temeke',
            'ward' => 'Vikindu',
            'physical_address' => 'Vikindu',
            'setup_completed_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, ServiceCategorySeeder::class, DentalFindingTypeSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    private function visit(User $admin, VisitStatus $status = VisitStatus::InQueue): Visit
    {
        $facility = currentFacility();
        $department = Department::query()->where('facility_id', $facility->id)->where('code', 'DEN')->firstOrFail();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'created_by' => $admin->id]);

        return Visit::factory()->create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => $department->id,
            'current_department_id' => $department->id,
            'visit_status' => $status,
            'created_by' => $admin->id,
        ]);
    }

    private function dentalService(string $name, string $code, User $admin): Service
    {
        $category = ServiceCategory::query()->first() ?: ServiceCategory::query()->create([
            'facility_id' => currentFacility()->id,
            'name' => 'Dental',
            'code' => 'DENT',
            'category_type' => 'procedure',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $service = Service::query()->create([
            'facility_id' => currentFacility()->id,
            'service_category_id' => $category->id,
            'name' => $name,
            'code' => $code,
            'service_type' => 'dental_service',
            'requires_payment' => true,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        ServicePrice::query()->create([
            'facility_id' => currentFacility()->id,
            'service_id' => $service->id,
            'payer_type' => 'cash',
            'amount' => 15000,
            'currency' => 'TZS',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        return $service;
    }
}
