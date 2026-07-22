<?php

namespace Tests\Feature\Laboratory;

use App\Enums\FacilityType;
use App\Enums\LaboratoryResultType;
use App\Enums\OwnershipType;
use App\Livewire\Laboratory\Dashboard;
use App\Livewire\Laboratory\Queue as LaboratoryQueue;
use App\Livewire\Laboratory\ResultEntry;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\LaboratoryOrder;
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
use App\Services\LaboratoryOrderService;
use App\Services\LaboratoryResultReleaseService;
use App\Services\LaboratoryResultService;
use App\Services\LaboratoryResultVerificationService;
use App\Services\LaboratorySampleService;
use App\Services\LaboratoryTestService;
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

    public function test_collect_and_accept_is_atomic_and_cannot_be_repeated(): void
    {
        $admin = $this->bootstrappedFacility();
        $test = $this->configuredTest($admin);
        $order = app(LaboratoryOrderService::class)->createOrder(
            $this->encounter($admin),
            ['service_ids' => [$test->service_id]],
            $admin,
        );
        $order->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $order->items()->update(['status' => 'ready_for_collection']);

        Livewire::actingAs($admin)
            ->test(LaboratoryQueue::class)
            ->call('openCollect', $order->id)
            ->assertSet('selectedOrder.id', $order->id)
            ->assertSet('sampleForm.order_item_ids', [$order->items()->firstOrFail()->id])
            ->call('collectAndAccept')
            ->assertHasNoErrors()
            ->assertSet('showCollectModal', false)
            ->assertDontSee($order->order_number);

        $sample = $order->samples()->firstOrFail();

        $this->assertSame('accepted', $sample->sample_status->value);
        $this->assertSame($admin->id, $sample->accepted_by);
        $this->assertNotNull($sample->accepted_at);
        $this->assertSame('sample_accepted', $order->items()->firstOrFail()->status);
        $this->assertSame('processing', $order->refresh()->status->value);

        try {
            app(LaboratorySampleService::class)->collectSample($order->refresh(), [], $admin, true);
            $this->fail('Duplicate collection was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order_item_ids', $exception->errors());
        }

        $this->assertDatabaseCount('laboratory_samples', 1);
    }

    public function test_empty_submitted_item_array_falls_back_to_active_order_items_and_accepts_legacy_status(): void
    {
        $admin = $this->bootstrappedFacility();
        $test = $this->configuredTest($admin);
        $order = app(LaboratoryOrderService::class)->createOrder(
            $this->encounter($admin),
            ['service_ids' => [$test->service_id]],
            $admin,
        );
        $order->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $order->items()->update(['status' => 'ordered']);

        $sample = app(LaboratorySampleService::class)->collectSample(
            $order->refresh(),
            ['order_item_ids' => []],
            $admin,
            true,
        );

        $this->assertSame('accepted', $sample->sample_status->value);
        $this->assertSame('sample_accepted', $order->items()->firstOrFail()->status);
    }

    public function test_paid_order_with_multiple_items_is_collected_and_order_creation_rejects_empty_selection(): void
    {
        $admin = $this->bootstrappedFacility();
        $firstTest = $this->configuredTest($admin);
        $secondService = $this->service('White Blood Cells', 'WBCTEST', 'laboratory_test', $admin);
        $secondTest = app(LaboratoryTestService::class)->createTest([
            'service_id' => $secondService->id,
            'laboratory_test_category_id' => $firstTest->laboratory_test_category_id,
            'specimen_type_id' => $firstTest->specimen_type_id,
            'name' => 'White Blood Cells',
            'code' => 'WBC',
            'result_type' => LaboratoryResultType::Numeric,
        ], $admin);
        $order = app(LaboratoryOrderService::class)->createOrder(
            $this->encounter($admin),
            ['service_ids' => [$firstTest->service_id, $secondTest->service_id]],
            $admin,
        );
        $order->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $order->items()->update(['status' => 'ready_for_collection']);

        $sample = app(LaboratorySampleService::class)->collectSample($order->refresh(), ['order_item_ids' => []], $admin, true);

        $this->assertSame(2, $sample->items()->count());
        $this->assertSame(['sample_accepted'], $order->items()->distinct()->pluck('status')->all());

        $ordersBefore = LaboratoryOrder::query()->count();
        try {
            app(LaboratoryOrderService::class)->createOrder($this->encounter($admin), ['service_ids' => []], $admin);
            $this->fail('An empty laboratory order was committed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('service_ids', $exception->errors());
        }
        $this->assertSame($ordersBefore, LaboratoryOrder::query()->count());
    }

    public function test_itemless_cancelled_mismatched_and_missing_specimen_orders_have_specific_errors(): void
    {
        $admin = $this->bootstrappedFacility();
        $test = $this->configuredTest($admin);

        $itemlessOrder = app(LaboratoryOrderService::class)->createOrder($this->encounter($admin), ['service_ids' => [$test->service_id]], $admin);
        $itemlessOrder->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $itemlessOrder->items()->firstOrFail()->forceDelete();
        $this->assertCollectionError(
            fn () => app(LaboratorySampleService::class)->collectSample($itemlessOrder->refresh(), ['order_item_ids' => []], $admin, true),
            'order_item_ids',
            'Order hii haina vipimo vilivyohifadhiwa.',
        );

        $cancelledOrder = app(LaboratoryOrderService::class)->createOrder($this->encounter($admin), ['service_ids' => [$test->service_id]], $admin);
        $cancelledOrder->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $cancelledOrder->items()->update(['status' => 'cancelled']);
        $this->assertCollectionError(
            fn () => app(LaboratorySampleService::class)->collectSample($cancelledOrder->refresh(), [], $admin, true),
            'order_item_ids',
            'Vipimo vya order hii vimefutwa.',
        );

        $mismatchedOrder = app(LaboratoryOrderService::class)->createOrder($this->encounter($admin), ['service_ids' => [$test->service_id]], $admin);
        $mismatchedOrder->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $mismatchedOrder->items()->update(['status' => 'ready_for_collection']);
        $this->assertCollectionError(
            fn () => app(LaboratorySampleService::class)->collectSample($mismatchedOrder->refresh(), ['order_item_ids' => [999999]], $admin, true),
            'order_item_ids',
            'Kitambulisho cha kipimo hakilingani na order hii.',
        );

        $missingSpecimenOrder = app(LaboratoryOrderService::class)->createOrder($this->encounter($admin), ['service_ids' => [$test->service_id]], $admin);
        $missingSpecimenOrder->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $missingSpecimenOrder->items()->update(['status' => 'ready_for_collection', 'specimen_type_id' => null]);
        $test->update(['specimen_type_id' => null]);
        $this->assertCollectionError(
            fn () => app(LaboratorySampleService::class)->collectSample($missingSpecimenOrder->refresh(), [], $admin, true),
            'specimen_type_id',
            'Aina ya sampuli haijawekwa',
        );
    }

    public function test_result_submission_shows_field_error_preserves_value_and_enters_verification_queue(): void
    {
        $admin = $this->bootstrappedFacility();
        $test = $this->configuredTest($admin);
        $order = app(LaboratoryOrderService::class)->createOrder(
            $this->encounter($admin),
            ['service_ids' => [$test->service_id]],
            $admin,
        );
        app(LaboratorySampleService::class)->collectSample($order, [], $admin, true);
        $parameter = $test->parameters()->firstOrFail();
        $field = "values.{$parameter->id}.value";

        $component = Livewire::actingAs($admin)
            ->test(ResultEntry::class, ['laboratoryOrder' => $order->refresh()])
            ->set($field, 'not-a-number')
            ->call('submitForVerification')
            ->assertHasErrors([$field])
            ->assertSet($field, 'not-a-number')
            ->assertDispatched('laboratory-validation-failed');

        $this->assertDatabaseCount('laboratory_results', 0);

        $component->set($field, '13.2')
            ->call('submitForVerification')
            ->assertHasNoErrors();

        $result = $order->results()->firstOrFail();
        $this->assertSame('pending_verification', $result->result_status->value);
        $this->assertNull($result->verified_at);
        $this->assertSame('sample_accepted', $order->items()->firstOrFail()->status);
        $this->assertSame('pending_verification', $order->items()->firstOrFail()->result_status);
        $this->assertSame('result_ready', $order->refresh()->status->value);
        $this->assertDatabaseHas('activity_logs', ['event' => 'result_submitted', 'subject_id' => $result->id]);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertSee($test->name);
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

    private function assertCollectionError(\Closure $action, string $field, string $message): void
    {
        try {
            $action();
            $this->fail('Laboratory sample collection unexpectedly succeeded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
            $this->assertStringContainsString($message, $exception->errors()[$field][0]);
        }
    }
}
