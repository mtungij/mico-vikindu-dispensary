<?php

namespace Tests\Feature\Laboratory;

use App\Enums\FacilityType;
use App\Enums\LaboratoryResultType;
use App\Enums\OwnershipType;
use App\Livewire\Laboratory\Queue as LaboratoryQueue;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestCategory;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\SpecimenType;
use App\Models\StaffProfile;
use App\Models\StaffSignature;
use App\Models\User;
use App\Models\Visit;
use App\Services\LaboratoryResultReleaseService;
use App\Services\LaboratoryResultService;
use App\Services\LaboratoryResultVerificationService;
use App\Services\LaboratorySampleService;
use App\Services\LaboratoryTestService;
use App\Services\LaboratoryOrderService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\LaboratorySampleRejectionReasonSeeder;
use Database\Seeders\LaboratoryTestCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SpecimenTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Step7LaboratoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_laboratory_queue(): void
    {
        $this->get(route('laboratory.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_access_laboratory_setup_and_queue(): void
    {
        $admin = $this->bootstrappedFacility();

        Livewire::actingAs($admin)->test(LaboratoryQueue::class)->assertOk();
        $this->actingAs($admin)->get(route('settings.laboratory.categories'))->assertOk();
        $this->actingAs($admin)->get(route('settings.laboratory.specimens'))->assertOk();
        $this->actingAs($admin)->get(route('settings.laboratory.tests'))->assertOk();
    }

    public function test_laboratory_test_setup_requires_laboratory_service_and_stores_parameters_ranges(): void
    {
        $admin = $this->bootstrappedFacility();
        $category = LaboratoryTestCategory::query()->forCurrentFacility()->firstOrFail();
        $specimen = SpecimenType::query()->forCurrentFacility()->firstOrFail();
        $consultation = $this->service('Consultation', 'CONS1', 'consultation', $admin);

        $this->expectException(ValidationException::class);
        app(LaboratoryTestService::class)->createTest([
            'service_id' => $consultation->id,
            'laboratory_test_category_id' => $category->id,
            'specimen_type_id' => $specimen->id,
            'name' => 'Invalid Lab Test',
            'code' => 'INVLAB',
            'result_type' => LaboratoryResultType::Numeric,
        ], $admin);
    }

    public function test_sample_result_verification_release_and_print_workflow(): void
    {
        $admin = $this->bootstrappedFacility();
        $test = $this->configuredTest($admin);
        $order = app(LaboratoryOrderService::class)->createOrder($this->encounter($admin), [
            'service_ids' => [$test->service_id],
            'clinical_notes' => 'Rule out severe anaemia',
        ], $admin);

        $item = $order->items()->firstOrFail();
        $this->assertSame($test->id, $item->laboratory_test_id);

        $sample = app(LaboratorySampleService::class)->collectSample($order, [
            'order_item_ids' => [$item->id],
            'specimen_type_id' => $test->specimen_type_id,
            'collected_at' => now()->format('Y-m-d H:i:s'),
        ], $admin, true);

        $this->assertStringStartsWith('SMP-', $sample->sample_number);
        $this->assertSame('accepted', $sample->sample_status->value);

        $resultService = app(LaboratoryResultService::class);
        $result = $resultService->createDraft($item->refresh(), $admin);
        $parameter = $test->parameters()->firstOrFail();
        $result = $resultService->saveValues($result, [
            (string) $parameter->id => ['value' => 5.1],
            'comments' => 'Critical low result',
        ], $admin, true);

        $this->assertSame('pending_verification', $result->result_status->value);
        $this->assertDatabaseHas('clinical_alerts', ['alert_type' => 'laboratory_critical_result', 'patient_id' => $order->patient_id]);
        $this->assertDatabaseHas('laboratory_critical_result_notifications', ['laboratory_result_id' => $result->id, 'status' => 'pending']);

        StaffProfile::query()->create([
            'user_id' => $admin->id,
            'facility_id' => currentFacility()->id,
            'employee_number' => 'EMP-LAB',
            'first_name' => 'Lab',
            'last_name' => 'Verifier',
            'gender' => 'male',
            'primary_phone' => '0712000000',
            'created_by' => $admin->id,
        ]);
        StaffSignature::query()->create([
            'facility_id' => currentFacility()->id,
            'staff_id' => $admin->staffProfile->id,
            'signature_path' => 'staff-signatures/demo.png',
            'uploaded_by' => $admin->id,
            'uploaded_at' => now(),
            'is_active' => true,
        ]);

        $verified = app(LaboratoryResultVerificationService::class)->verify($result->refresh(), $admin);
        $released = app(LaboratoryResultReleaseService::class)->release($verified, $admin);

        $this->assertSame('released', $released->result_status->value);
        $this->assertSame('completed', $order->refresh()->status->value);
        $this->assertDatabaseHas('activity_logs', ['event' => 'result_verified', 'subject_id' => $result->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'result_released', 'subject_id' => $result->id]);

        $this->actingAs($admin)->get(route('laboratory.results.print', $released))->assertOk()->assertSee('Laboratory Result');
        $this->actingAs($admin)->get(route('laboratory.orders.report', $order))->assertOk()->assertSee('Laboratory Report');
    }

    public function test_rejected_sample_cannot_receive_results(): void
    {
        $admin = $this->bootstrappedFacility();
        $test = $this->configuredTest($admin);
        $order = app(LaboratoryOrderService::class)->createOrder($this->encounter($admin), ['service_ids' => [$test->service_id]], $admin);
        $sample = app(LaboratorySampleService::class)->collectSample($order, ['order_item_ids' => [$order->items()->first()->id]], $admin, false);

        $this->expectException(ValidationException::class);
        app(LaboratoryResultService::class)->createDraft($order->items()->first()->refresh(), $admin);
    }

    public function test_laboratory_reports_and_clinician_review_routes_render(): void
    {
        $admin = $this->bootstrappedFacility();

        foreach (['orders', 'tests', 'samples', 'results', 'critical-results', 'revenue', 'turnaround-time'] as $type) {
            $this->actingAs($admin)->get(route('reports.laboratory', $type))->assertOk();
            $this->actingAs($admin)->get(route('reports.laboratory.export', $type))->assertOk();
        }

        $this->actingAs($admin)->get(route('laboratory.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('laboratory.critical-results'))->assertOk();
        $this->actingAs($admin)->get(route('clinical.laboratory-results'))->assertOk();
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
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
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->seed([
            PermissionSeeder::class,
            DepartmentSeeder::class,
            LaboratoryTestCategorySeeder::class,
            SpecimenTypeSeeder::class,
            LaboratorySampleRejectionReasonSeeder::class,
        ]);

        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    private function configuredTest(User $admin): LaboratoryTest
    {
        $category = LaboratoryTestCategory::query()->forCurrentFacility()->firstOrFail();
        $specimen = SpecimenType::query()->forCurrentFacility()->firstOrFail();
        $service = $this->service('Haemoglobin', 'HBTEST', 'laboratory_test', $admin);
        $test = app(LaboratoryTestService::class)->createTest([
            'service_id' => $service->id,
            'laboratory_test_category_id' => $category->id,
            'specimen_type_id' => $specimen->id,
            'name' => 'Haemoglobin',
            'code' => 'HB',
            'result_type' => LaboratoryResultType::Numeric,
            'unit' => 'g/dL',
            'turnaround_time_minutes' => 60,
        ], $admin);

        $parameter = app(LaboratoryTestService::class)->addParameter($test, [
            'name' => 'Haemoglobin',
            'code' => 'HB',
            'result_type' => LaboratoryResultType::Numeric,
            'unit' => 'g/dL',
            'critical_low' => 6,
            'critical_high' => 20,
            'is_required' => true,
            'show_on_report' => true,
        ], $admin);

        LaboratoryReferenceRange::query()->create([
            'facility_id' => currentFacility()->id,
            'laboratory_test_id' => $test->id,
            'laboratory_test_parameter_id' => $parameter->id,
            'lower_limit' => 12,
            'upper_limit' => 16,
            'unit' => 'g/dL',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        return $test->refresh();
    }

    private function patient(User $admin): Patient
    {
        return Patient::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_number' => 'PAT-2026-'.fake()->unique()->numerify('######'),
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'male',
            'age_years' => 30,
            'patient_status' => 'active',
            'created_by' => $admin->id,
            'registered_at' => now(),
        ]);
    }

    private function visit(User $admin): Visit
    {
        $department = Department::query()->forCurrentFacility()->firstOrFail();

        return Visit::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $this->patient($admin)->id,
            'visit_number' => 'VIS-2026-'.fake()->unique()->numerify('######'),
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => $department->id,
            'current_department_id' => $department->id,
            'visit_status' => 'in_consultation',
            'priority' => 'normal',
            'registered_at' => now(),
            'created_by' => $admin->id,
        ]);
    }

    private function encounter(User $admin): ClinicalEncounter
    {
        $visit = $this->visit($admin);

        return ClinicalEncounter::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->id,
            'department_id' => $visit->current_department_id,
            'encounter_type' => 'opd',
            'encounter_number' => 'ENC-2026-'.fake()->unique()->numerify('######'),
            'provider_user_id' => $admin->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'created_by' => $admin->id,
        ]);
    }

    private function service(string $name, string $code, string $type, User $admin): Service
    {
        $category = ServiceCategory::query()->first() ?: ServiceCategory::query()->create([
            'facility_id' => currentFacility()->id,
            'name' => 'Laboratory',
            'code' => 'LAB',
            'category_type' => 'laboratory',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $service = Service::query()->create([
            'facility_id' => currentFacility()->id,
            'service_category_id' => $category->id,
            'name' => $name,
            'code' => $code,
            'service_type' => $type,
            'requires_payment' => true,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        ServicePrice::query()->create([
            'facility_id' => currentFacility()->id,
            'service_id' => $service->id,
            'payer_type' => 'cash',
            'amount' => 1000,
            'currency' => 'TZS',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        return $service;
    }
}
