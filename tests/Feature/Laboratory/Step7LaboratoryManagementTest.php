<?php

namespace Tests\Feature\Laboratory;

use App\Enums\FacilityType;
use App\Enums\LaboratoryResultType;
use App\Enums\OwnershipType;
use App\Livewire\Laboratory\Dashboard;
use App\Livewire\Laboratory\OrderShow;
use App\Livewire\Laboratory\Queue as LaboratoryQueue;
use App\Livewire\Laboratory\ResultEntry;
use App\Livewire\Laboratory\VerifyResult;
use App\Livewire\Patients\Show as PatientShow;
use App\Livewire\Reception\Queue as ReceptionQueue;
use App\Models\ActivityLog;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestCategory;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\Permission;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\SpecimenType;
use App\Models\StaffProfile;
use App\Models\StaffSignature;
use App\Models\User;
use App\Models\Visit;
use App\Models\WorkflowSetting;
use App\Services\ClinicalEncounterService;
use App\Services\DiagnosisService;
use App\Services\LaboratoryOrderService;
use App\Services\LaboratoryReportService;
use App\Services\LaboratoryResultReleaseService;
use App\Services\LaboratoryResultService;
use App\Services\LaboratoryResultVerificationService;
use App\Services\LaboratorySampleService;
use App\Services\LaboratoryTestService;
use App\Services\VisitClosureService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\LaboratorySampleRejectionReasonSeeder;
use Database\Seeders\LaboratoryTestCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
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
        $encounter = $this->encounter($admin);
        $order = app(LaboratoryOrderService::class)->createOrder($encounter, [
            'service_ids' => [$test->service_id],
            'clinical_notes' => 'Rule out severe anaemia',
        ], $admin);
        $order->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $order->visit->invoice()->update([
            'balance_amount' => 0,
            'payment_status' => 'paid',
            'invoice_status' => 'paid',
        ]);
        $encounter->update([
            'status' => 'completed',
            'signed_off_by' => $admin->id,
            'signed_off_at' => now(),
            'completed_by' => $admin->id,
            'completed_at' => now(),
        ]);
        $laboratory = Department::query()->forCurrentFacility()->where('code', 'LAB')->firstOrFail();
        $queue = PatientQueue::query()->create([
            'facility_id' => currentFacility()->id,
            'visit_id' => $order->visit_id,
            'patient_id' => $order->patient_id,
            'department_id' => $laboratory->id,
            'queue_number' => 'LAB-TEST-001',
            'queue_date' => today(),
            'queue_status' => 'waiting',
            'priority' => 'normal',
            'position' => 1,
            'checked_in_at' => now(),
            'created_by' => $admin->id,
        ]);
        $order->visit->update([
            'visit_status' => 'awaiting_lab',
            'current_department_id' => $laboratory->id,
            'current_queue_id' => $queue->id,
        ]);

        $item = $order->items()->firstOrFail();
        $this->assertSame($test->id, $item->laboratory_test_id);

        $sample = app(LaboratorySampleService::class)->collectSample($order, [
            'order_item_ids' => [$item->id],
            'specimen_type_id' => $test->specimen_type_id,
            'collected_at' => now()->format('Y-m-d H:i:s'),
        ], $admin, true);

        $this->assertStringStartsWith('SMP-', $sample->sample_number);
        $this->assertSame('accepted', $sample->sample_status->value);
        $this->assertContains($queue->refresh()->queue_status->value, ['waiting', 'called', 'serving']);
        $this->assertNotSame('completed', $queue->queue_status->value);
        $this->assertSame('processing', $order->refresh()->status->value);
        $this->assertNotSame('completed', $order->status->value);
        $this->assertNotSame('completed', $order->visit->refresh()->visit_status->value);

        $resultService = app(LaboratoryResultService::class);
        $result = $resultService->createDraft($item->refresh(), $admin);
        $this->assertContains($queue->refresh()->queue_status->value, ['waiting', 'called', 'serving']);
        $parameter = $test->parameters()->firstOrFail();
        $result = $resultService->saveValues($result, [
            (string) $parameter->id => ['value' => 5.1],
            'comments' => 'Critical low result',
        ], $admin, true);

        $this->assertSame('pending_verification', $result->result_status->value);
        $this->assertContains($queue->refresh()->queue_status->value, ['waiting', 'called', 'serving']);
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
        $this->assertSame('verified', $verified->result_status->value);
        $this->assertContains($queue->refresh()->queue_status->value, ['waiting', 'called', 'serving']);
        $released = app(LaboratoryResultReleaseService::class)->release($verified, $admin);

        $this->assertSame('released', $released->result_status->value);
        $this->assertSame('completed', $order->refresh()->status->value);
        $this->assertSame('completed', $queue->refresh()->queue_status->value);
        $this->assertSame(0, PatientQueue::query()
            ->where('visit_id', $order->visit_id)
            ->where('department_id', $laboratory->id)
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->count());
        $this->assertSame('completed', $order->visit->refresh()->visit_status->value);
        $this->assertSame('completed', $encounter->refresh()->status->value);
        $this->assertNotNull($encounter->completed_at);
        $this->assertDatabaseHas('activity_logs', ['event' => 'result_verified', 'subject_id' => $result->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'result_released', 'subject_id' => $result->id]);

        $this->actingAs($admin)->get(route('laboratory.results.print', $released))->assertOk()->assertSee('Laboratory Result');
        $this->actingAs($admin)->get(route('laboratory.orders.report', $order))->assertOk()->assertSee('Laboratory Report');
    }

    public function test_order_report_is_blocked_until_every_result_is_released(): void
    {
        $admin = $this->bootstrappedFacility();
        $test = $this->configuredTest($admin);
        $order = app(LaboratoryOrderService::class)->createOrder(
            $this->encounter($admin),
            ['service_ids' => [$test->service_id]],
            $admin,
        );

        $this->actingAs($admin)
            ->get(route('laboratory.orders.report.download', $order))
            ->assertStatus(422)
            ->assertSee('Majibu ya vipimo bado hayajakamilika au kuthibitishwa.');

        $this->assertNull($order->refresh()->report_number);
        $this->assertDatabaseMissing('activity_logs', [
            'event' => 'laboratory_report_downloaded',
            'subject_id' => $order->id,
        ]);
    }

    public function test_released_report_can_be_viewed_downloaded_and_printed_with_stable_number_and_audit(): void
    {
        $admin = $this->bootstrappedFacility();
        $test = $this->configuredTest($admin);
        $encounter = $this->encounter($admin);
        $order = app(LaboratoryOrderService::class)->createOrder(
            $encounter,
            ['service_ids' => [$test->service_id]],
            $admin,
        );
        $order->update([
            'clinical_encounter_id' => null,
            'source' => 'reception_direct',
            'status' => 'ordered',
            'payment_status' => 'paid',
        ]);
        $order->visit->invoice()->update([
            'balance_amount' => 0,
            'payment_status' => 'paid',
            'invoice_status' => 'paid',
        ]);
        $encounter->delete();
        $queue = $this->createLaboratoryQueue($order, $admin);
        WorkflowSetting::query()->create([
            'facility_id' => currentFacility()->id,
            'key' => 'require_doctor_review_after_laboratory',
            'value' => [true],
            'type' => 'boolean',
            'group' => 'workflow',
            'updated_by' => $admin->id,
        ]);
        $this->assertFalse(app(VisitClosureService::class)->requiresDoctorReview($order->visit));
        app(LaboratorySampleService::class)->collectSample($order, [], $admin, true);
        $this->assertFalse(app(LaboratoryReportService::class)->isEligible($order->refresh()));
        $this->assertContains($queue->refresh()->queue_status->value, ['waiting', 'called', 'serving']);
        $item = $order->items()->firstOrFail();
        $parameter = $test->parameters()->firstOrFail();
        $result = app(LaboratoryResultService::class)->createDraft($item->refresh(), $admin);
        $result = app(LaboratoryResultService::class)->saveValues($result, [
            (string) $parameter->id => ['value' => 13.5],
            'comments' => 'Within the reference range',
        ], $admin, true);
        $result = app(LaboratoryResultVerificationService::class)->verify($result, $admin);
        $result = app(LaboratoryResultReleaseService::class)->release($result, $admin);
        $this->assertTrue(app(LaboratoryReportService::class)->isEligible($order->refresh()));
        $this->assertSame('completed', $queue->refresh()->queue_status->value);
        $this->assertSame('completed', $order->visit->refresh()->visit_status->value);
        $this->assertNotSame('awaiting_doctor_review', $order->visit->visit_status->value);
        $this->assertSame(0, PatientQueue::query()
            ->where('visit_id', $order->visit_id)
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->whereHas('department', fn ($query) => $query->where('code', 'OPD'))
            ->count());

        $this->actingAs($admin)
            ->get(route('laboratory.orders.report.view', $order))
            ->assertOk()
            ->assertSee('LABORATORY RESULTS REPORT')
            ->assertSee('Direct Laboratory')
            ->assertSee('13.5');

        $reportNumber = $order->refresh()->report_number;
        $this->assertMatchesRegularExpression('/^LAB-RPT-\d{4}-\d{6}$/', $reportNumber);

        $this->actingAs($admin)
            ->get(route('laboratory.orders.report.download', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', "attachment; filename=\"{$reportNumber}-R1.pdf\"");

        $this->actingAs($admin)
            ->get(route('laboratory.orders.report.print', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', "inline; filename=\"{$reportNumber}-R1.pdf\"");

        $result->update(['result_version' => 2]);
        $this->actingAs($admin)->get(route('laboratory.orders.report.view', $order))->assertOk()->assertSee('Revision 2');
        $this->assertSame($reportNumber, $order->refresh()->report_number);
        $this->assertSame(2, $order->report_revision);
        $this->assertDatabaseHas('activity_logs', ['event' => 'laboratory_report_viewed', 'subject_id' => $order->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'laboratory_report_downloaded', 'subject_id' => $order->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'laboratory_report_printed', 'subject_id' => $order->id]);
    }

    public function test_multiple_test_order_keeps_queue_active_until_every_result_is_released_and_retries_are_idempotent(): void
    {
        $admin = $this->bootstrappedFacility();
        $firstTest = $this->configuredTest($admin);
        $secondTest = $this->additionalConfiguredTest($admin, $firstTest, 'Platelets', 'PLT');
        $encounter = $this->encounter($admin);
        $order = app(LaboratoryOrderService::class)->createOrder($encounter, [
            'service_ids' => [$firstTest->service_id, $secondTest->service_id],
        ], $admin);
        $order->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $order->items()->update(['status' => 'ready_for_collection']);
        $order->visit->invoice()->update([
            'balance_amount' => 0,
            'payment_status' => 'paid',
            'invoice_status' => 'paid',
        ]);
        $encounter->update([
            'status' => 'completed',
            'signed_off_by' => $admin->id,
            'signed_off_at' => now(),
            'completed_by' => $admin->id,
            'completed_at' => now(),
        ]);
        $queue = $this->createLaboratoryQueue($order, $admin);
        $firstItem = $order->items()->where('laboratory_test_id', $firstTest->id)->firstOrFail();
        $secondItem = $order->items()->where('laboratory_test_id', $secondTest->id)->firstOrFail();

        app(LaboratorySampleService::class)->collectSample(
            $order,
            ['order_item_ids' => [$firstItem->id]],
            $admin,
            true,
        );

        $this->assertNotNull($firstItem->refresh()->sample_id);
        $this->assertNull($secondItem->refresh()->sample_id);
        $this->assertSame('processing', $order->refresh()->status->value);
        $this->assertContains($queue->refresh()->queue_status->value, ['waiting', 'called', 'serving']);
        $this->assertNotSame('completed', $order->visit->refresh()->visit_status->value);

        app(LaboratorySampleService::class)->collectSample(
            $order->refresh(),
            ['order_item_ids' => [$secondItem->id]],
            $admin,
            true,
        );
        $this->assertContains($queue->refresh()->queue_status->value, ['waiting', 'called', 'serving']);

        $firstResult = $this->submitResult($firstItem->refresh(), $firstTest, 13.4, $admin);
        $firstResult = app(LaboratoryResultVerificationService::class)->verify($firstResult, $admin);
        $this->assertContains($queue->refresh()->queue_status->value, ['waiting', 'called', 'serving']);
        $firstResult = app(LaboratoryResultReleaseService::class)->release($firstResult, $admin);

        $this->assertNotSame('completed', $order->refresh()->status->value);
        $this->assertContains($queue->refresh()->queue_status->value, ['waiting', 'called', 'serving']);
        $this->assertNotSame('completed', $order->visit->refresh()->visit_status->value);
        $this->actingAs($admin)
            ->get(route('laboratory.orders.report.download', $order))
            ->assertStatus(422)
            ->assertSee(LaboratoryReportService::INCOMPLETE_MESSAGE);

        $secondResult = $this->submitResult($secondItem->refresh(), $secondTest, 250, $admin);
        $secondResult = app(LaboratoryResultVerificationService::class)->verify($secondResult, $admin);
        $this->assertNotSame('completed', $queue->refresh()->queue_status->value);
        $secondResult = app(LaboratoryResultReleaseService::class)->release($secondResult, $admin);

        $this->assertSame('completed', $order->refresh()->status->value);
        $this->assertSame('completed', $queue->refresh()->queue_status->value);
        $this->assertSame('completed', $order->visit->refresh()->visit_status->value);
        $this->assertSame(0, PatientQueue::query()
            ->where('visit_id', $order->visit_id)
            ->where('department_id', $queue->department_id)
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->count());
        $this->actingAs($admin)
            ->get(route('laboratory.orders.report.download', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $releaseAuditCount = $this->activityCount('result_released', $secondResult->id);
        $queueCompletionAuditCount = $this->activityCount('queue_completed', $queue->id);
        $retriedVerification = app(LaboratoryResultVerificationService::class)->verify($secondResult->refresh(), $admin);
        $retriedRelease = app(LaboratoryResultReleaseService::class)->release($secondResult->refresh(), $admin);

        $this->assertSame('released', $retriedVerification->result_status->value);
        $this->assertSame('released', $retriedRelease->result_status->value);
        $this->assertSame($releaseAuditCount, $this->activityCount('result_released', $secondResult->id));
        $this->assertSame($queueCompletionAuditCount, $this->activityCount('queue_completed', $queue->id));
    }

    public function test_doctor_ordered_final_release_closes_lab_queue_but_keeps_active_consultation_open(): void
    {
        $admin = $this->bootstrappedFacility();
        $test = $this->configuredTest($admin);
        $encounter = $this->encounter($admin);
        $order = app(LaboratoryOrderService::class)->createOrder(
            $encounter,
            ['service_ids' => [$test->service_id]],
            $admin,
        );
        $order->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $order->items()->update(['status' => 'ready_for_collection']);
        $order->visit->invoice()->update([
            'balance_amount' => 0,
            'payment_status' => 'paid',
            'invoice_status' => 'paid',
        ]);
        $queue = $this->createLaboratoryQueue($order, $admin);

        app(LaboratorySampleService::class)->collectSample($order, [], $admin, true);
        $result = $this->submitResult($order->items()->firstOrFail()->refresh(), $test, 14.1, $admin);
        $result = app(LaboratoryResultVerificationService::class)->verify($result, $admin);
        app(LaboratoryResultReleaseService::class)->release($result, $admin);

        $this->assertSame('completed', $order->refresh()->status->value);
        $this->assertSame('completed', $queue->refresh()->queue_status->value);
        $this->assertSame('in_progress', $encounter->refresh()->status->value);
        $this->assertNull($encounter->completed_at);
        $this->assertSame('in_consultation', $order->visit->refresh()->visit_status->value);
        $this->assertNull($order->visit->completed_at);
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

    public function test_required_laboratory_review_creates_new_opd_review_encounter_without_reopening_original(): void
    {
        $admin = $this->bootstrappedFacility();
        $test = $this->configuredTest($admin);
        $original = $this->encounter($admin);
        $order = app(LaboratoryOrderService::class)->createOrder($original, [
            'service_ids' => [$test->service_id],
        ], $admin);
        $order->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $order->visit->invoice()->update([
            'balance_amount' => 0,
            'payment_status' => 'paid',
            'invoice_status' => 'paid',
        ]);
        $original->update([
            'status' => 'completed',
            'clinical_summary' => 'Original completed consultation',
            'outcome' => 'discharged_home',
            'signed_off_by' => $admin->id,
            'signed_off_at' => now(),
            'completed_by' => $admin->id,
            'completed_at' => now(),
        ]);
        WorkflowSetting::query()->create([
            'facility_id' => currentFacility()->id,
            'key' => 'require_doctor_review_after_laboratory',
            'value' => [true],
            'type' => 'boolean',
            'group' => 'workflow',
            'updated_by' => $admin->id,
        ]);

        app(LaboratorySampleService::class)->collectSample($order, [], $admin, true);
        $this->assertSame('awaiting_results', $order->visit->refresh()->visit_status->value);

        $item = $order->items()->firstOrFail();
        $parameter = $test->parameters()->firstOrFail();
        $result = app(LaboratoryResultService::class)->createDraft($item->refresh(), $admin);
        $result = app(LaboratoryResultService::class)->saveValues($result, [
            (string) $parameter->id => ['value' => 13.5],
        ], $admin, true);
        $verified = app(LaboratoryResultVerificationService::class)->verify($result, $admin);
        $released = app(LaboratoryResultReleaseService::class)->release($verified, $admin);

        $this->assertSame('completed', $original->refresh()->status->value);
        $this->assertNotNull($original->completed_at);
        $this->assertSame('awaiting_doctor_review', $order->visit->refresh()->visit_status->value);
        $this->assertDatabaseHas('patient_queues', [
            'visit_id' => $order->visit_id,
            'department_id' => Department::query()->forCurrentFacility()->where('code', 'OPD')->value('id'),
            'queue_status' => 'waiting',
        ]);

        $review = app(ClinicalEncounterService::class)->startEncounter($order->visit->refresh(), $admin);
        $this->assertSame($original->id, $review->parent_encounter_id);
        $this->assertSame('follow_up', $review->encounter_type->value);
        app(ClinicalEncounterService::class)->saveDraft($review, [
            'clinical_summary' => 'Laboratory result reviewed with patient',
            'outcome' => 'discharged_home',
        ], $admin);
        app(DiagnosisService::class)->addDiagnosis($review->refresh(), [
            'diagnosis_type' => 'final',
            'diagnosis_name' => 'Laboratory review',
            'certainty' => 'confirmed',
            'is_primary' => true,
        ], $admin);
        app(ClinicalEncounterService::class)->signOff($review->refresh(), $admin);
        app(ClinicalEncounterService::class)->completeEncounter($review->refresh(), $admin);

        $this->assertNotNull($released->refresh()->reviewed_at);
        $this->assertSame('completed', $order->visit->refresh()->visit_status->value);
        $this->assertSame('completed', $original->refresh()->status->value);
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
            ->assertSet('sampleForm.order_item_ids', [])
            ->set('sampleForm.order_item_ids', [$order->items()->firstOrFail()->id])
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

    public function test_empty_submitted_item_array_is_rejected(): void
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

        $this->assertCollectionError(
            fn () => app(LaboratorySampleService::class)->collectSample($order->refresh(), ['order_item_ids' => []], $admin, true),
            'order_item_ids',
            'Chagua angalau kipimo kimoja cha kukusanyia sampuli.',
        );
        $this->assertDatabaseCount('laboratory_samples', 0);
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

        $itemIds = $order->items()->pluck('id')->all();
        $sample = app(LaboratorySampleService::class)->collectSample($order->refresh(), ['order_item_ids' => $itemIds], $admin, true);

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

    public function test_partial_collection_keeps_sibling_awaiting_and_restores_item_result_entry(): void
    {
        $admin = $this->bootstrappedFacility();
        $firstTest = $this->configuredTest($admin);
        $secondService = $this->service('Platelets', 'PLTTEST', 'laboratory_test', $admin);
        $secondTest = app(LaboratoryTestService::class)->createTest([
            'service_id' => $secondService->id,
            'laboratory_test_category_id' => $firstTest->laboratory_test_category_id,
            'specimen_type_id' => $firstTest->specimen_type_id,
            'name' => 'Platelets',
            'code' => 'PLT',
            'result_type' => LaboratoryResultType::Numeric,
        ], $admin);
        $order = app(LaboratoryOrderService::class)->createOrder(
            $this->encounter($admin),
            ['service_ids' => [$firstTest->service_id, $secondTest->service_id]],
            $admin,
        );
        $order->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $order->items()->update(['status' => 'ready_for_collection']);
        $firstItem = $order->items()->where('laboratory_test_id', $firstTest->id)->firstOrFail();
        $secondItem = $order->items()->where('laboratory_test_id', $secondTest->id)->firstOrFail();

        app(LaboratorySampleService::class)->collectSample(
            $order->refresh(),
            ['order_item_ids' => [$firstItem->id]],
            $admin,
            true,
        );

        $this->assertSame('sample_accepted', $firstItem->refresh()->status);
        $this->assertNull($secondItem->refresh()->sample_id);
        $this->assertSame('ready_for_collection', $secondItem->status);
        $this->assertSame('processing', $order->refresh()->status->value);

        Livewire::actingAs($admin)->test(LaboratoryQueue::class)
            ->assertSee($secondTest->name)
            ->set('tab', 'processing')
            ->assertSee($firstTest->name)
            ->assertSee('Ingiza Matokeo');

        Livewire::actingAs($admin)->test(ResultEntry::class, ['laboratoryOrder' => $order->refresh()])
            ->assertSet('itemId', $firstItem->id)
            ->assertSee($secondTest->name)
            ->assertSee('Sampuli haijakusanywa');
    }

    public function test_incompatible_selected_specimens_are_split_into_separate_samples(): void
    {
        $admin = $this->bootstrappedFacility();
        $firstTest = $this->configuredTest($admin);
        $otherSpecimen = SpecimenType::query()->forCurrentFacility()->whereKeyNot($firstTest->specimen_type_id)->firstOrFail();
        $secondService = $this->service('Urinalysis', 'URITEST', 'laboratory_test', $admin);
        $secondTest = app(LaboratoryTestService::class)->createTest([
            'service_id' => $secondService->id,
            'laboratory_test_category_id' => $firstTest->laboratory_test_category_id,
            'specimen_type_id' => $otherSpecimen->id,
            'name' => 'Urinalysis',
            'code' => 'URI',
            'result_type' => LaboratoryResultType::Text,
        ], $admin);
        $order = app(LaboratoryOrderService::class)->createOrder(
            $this->encounter($admin),
            ['service_ids' => [$firstTest->service_id, $secondTest->service_id]],
            $admin,
        );
        $order->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $order->items()->update(['status' => 'ready_for_collection']);

        app(LaboratorySampleService::class)->collectSample(
            $order->refresh(),
            ['order_item_ids' => $order->items()->pluck('id')->all()],
            $admin,
            true,
        );

        $this->assertCount(2, $order->samples()->get());
        $this->assertSame(2, $order->items()->distinct()->pluck('sample_id')->count());
        $this->assertSame(['sample_accepted'], $order->items()->distinct()->pluck('status')->all());
    }

    public function test_itemless_cancelled_mismatched_and_missing_specimen_orders_have_specific_errors(): void
    {
        $admin = $this->bootstrappedFacility();
        $test = $this->configuredTest($admin);

        $itemlessOrder = app(LaboratoryOrderService::class)->createOrder($this->encounter($admin), ['service_ids' => [$test->service_id]], $admin);
        $itemlessOrder->update(['status' => 'ordered', 'payment_status' => 'paid']);
        $itemlessOrder->items()->firstOrFail()->forceDelete();
        $this->assertCollectionError(
            fn () => app(LaboratorySampleService::class)->collectSample($itemlessOrder->refresh(), [], $admin, true),
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
            'order_item_ids.'.$missingSpecimenOrder->items()->firstOrFail()->id,
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

    public function test_direct_laboratory_result_requires_release_then_refreshes_report_actions(): void
    {
        $admin = $this->bootstrappedFacility();
        [$order, $result] = $this->directLaboratoryOrderWithResult($admin);

        $component = Livewire::actingAs($admin)
            ->test(VerifyResult::class, ['laboratoryResult' => $result])
            ->assertSee('Verify')
            ->assertDontSee('Release Results')
            ->assertDontSee('Pakua Majibu')
            ->call('verify')
            ->assertSee('Release Results')
            ->assertDontSee('Pakua Majibu')
            ->call('release')
            ->assertDispatched('laboratory-result-updated')
            ->assertSee('Angalia Majibu')
            ->assertSee('Pakua Majibu')
            ->assertSee('Chapisha Majibu');

        $component->assertHasNoErrors();
        $this->assertTrue(app(LaboratoryReportService::class)->isEligible($order->refresh()));
        $this->assertSame('completed', $order->status->value);
        $this->assertSame('released', $result->refresh()->result_status->value);
        $this->assertNotNull($result->released_at);
        $this->assertSame($admin->id, $result->released_by);

        Livewire::actingAs($admin)
            ->test(OrderShow::class, ['laboratoryOrder' => $order->refresh()])
            ->set('tab', 'results')
            ->assertSee('Angalia Majibu')
            ->assertSee('Pakua Majibu')
            ->assertSee('Chapisha Majibu');

        Livewire::actingAs($admin)
            ->test(LaboratoryQueue::class)
            ->set('tab', 'completed')
            ->assertSee('Angalia Majibu')
            ->assertSee('Pakua Majibu')
            ->assertSee('Chapisha Majibu');

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->set('worklistTab', 'released')
            ->assertSee('Angalia Majibu')
            ->assertSee('Pakua Majibu')
            ->assertSee('Chapisha Majibu');

        Livewire::actingAs($admin)
            ->test(PatientShow::class, ['patient' => $order->patient])
            ->set('tab', 'laboratory')
            ->assertSee('Angalia Majibu')
            ->assertSee('Pakua Majibu')
            ->assertSee('Chapisha Majibu');

        Livewire::actingAs($admin)
            ->test(ReceptionQueue::class)
            ->assertSee('Angalia Majibu')
            ->assertSee('Pakua Majibu')
            ->assertSee('Chapisha Majibu');
    }

    public function test_dashboard_worklists_keep_old_pending_results_actionable_and_move_released_results_to_history(): void
    {
        $admin = $this->bootstrappedFacility();
        [$order, $result] = $this->directLaboratoryOrderWithResult($admin);
        $order->patient->update(['first_name' => 'Worklist', 'last_name' => 'Patient']);
        $result->update(['entered_at' => now()->subDays(90)]);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertSee('Worklist Patient')
            ->assertSee('Awaiting Verification')
            ->set('search', $order->order_number)
            ->assertSee('Worklist Patient')
            ->set('search', 'Worklist')
            ->assertSee($order->order_number)
            ->set('sourceFilter', LaboratoryOrder::SOURCE_RECEPTION_DIRECT)
            ->assertSee('Direct Reception Laboratory');

        $verified = app(LaboratoryResultVerificationService::class)->verify($result->refresh(), $admin);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertDontSee('Worklist Patient')
            ->set('worklistTab', 'verified')
            ->assertSee('Worklist Patient')
            ->assertSee('Release Results');

        app(LaboratoryResultReleaseService::class)->release($verified->refresh(), $admin);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertDontSee('Worklist Patient')
            ->set('worklistTab', 'verified')
            ->assertDontSee('Worklist Patient')
            ->set('worklistTab', 'released')
            ->assertSee('Worklist Patient')
            ->assertSee('Released / Completed');
    }

    public function test_reception_and_laboratory_users_can_download_released_direct_report(): void
    {
        $admin = $this->bootstrappedFacility();
        [$order, $result] = $this->directLaboratoryOrderWithResult($admin);
        $result = app(LaboratoryResultVerificationService::class)->verify($result, $admin);
        app(LaboratoryResultReleaseService::class)->release($result, $admin);

        $this->seed([RoleSeeder::class, RolePermissionSeeder::class]);

        foreach (['receptionist', 'laboratory-technician'] as $role) {
            $user = User::factory()->create();
            StaffProfile::factory()->create([
                'user_id' => $user->id,
                'facility_id' => currentFacility()->id,
            ]);
            $user->assignRole($role);

            $this->assertTrue($user->can('laboratory-results.download'));
            $this->actingAs($user)
                ->get(route('laboratory.orders.report.download', $order))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }

        $unauthorized = User::factory()->create();
        StaffProfile::factory()->create([
            'user_id' => $unauthorized->id,
            'facility_id' => currentFacility()->id,
        ]);
        $unauthorized->givePermissionTo('laboratory-results.view');

        $this->actingAs($unauthorized)
            ->get(route('laboratory.orders.report.download', $order))
            ->assertForbidden();
    }

    public function test_user_with_laboratory_results_view_permission_can_open_clinical_results(): void
    {
        $this->bootstrappedFacility();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('laboratory-results.view');

        $this->actingAs($viewer)
            ->get(route('clinical.laboratory-results'))
            ->assertOk();
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

    /** @return array{LaboratoryOrder, LaboratoryResult} */
    private function directLaboratoryOrderWithResult(User $admin): array
    {
        $test = $this->configuredTest($admin);
        $encounter = $this->encounter($admin);
        $order = app(LaboratoryOrderService::class)->createOrder(
            $encounter,
            ['service_ids' => [$test->service_id]],
            $admin,
        );
        $order->update([
            'clinical_encounter_id' => null,
            'source' => LaboratoryOrder::SOURCE_RECEPTION_DIRECT,
            'status' => 'ordered',
            'payment_status' => 'paid',
        ]);
        $order->visit->invoice()->update([
            'balance_amount' => 0,
            'payment_status' => 'paid',
            'invoice_status' => 'paid',
        ]);
        $encounter->delete();
        $this->createLaboratoryQueue($order, $admin);
        app(LaboratorySampleService::class)->collectSample($order, [], $admin, true);

        $item = $order->items()->firstOrFail();
        $parameter = $test->parameters()->firstOrFail();
        $result = app(LaboratoryResultService::class)->createDraft($item->refresh(), $admin);
        $result = app(LaboratoryResultService::class)->saveValues($result, [
            (string) $parameter->id => ['value' => 13.5],
        ], $admin, true);

        return [$order->refresh(), $result->refresh()];
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

    private function additionalConfiguredTest(
        User $admin,
        LaboratoryTest $baseTest,
        string $name,
        string $code,
    ): LaboratoryTest {
        $service = $this->service($name, $code.'TEST', 'laboratory_test', $admin);
        $test = app(LaboratoryTestService::class)->createTest([
            'service_id' => $service->id,
            'laboratory_test_category_id' => $baseTest->laboratory_test_category_id,
            'specimen_type_id' => $baseTest->specimen_type_id,
            'name' => $name,
            'code' => $code,
            'result_type' => LaboratoryResultType::Numeric,
            'unit' => '10^9/L',
        ], $admin);
        app(LaboratoryTestService::class)->addParameter($test, [
            'name' => $name,
            'code' => $code,
            'result_type' => LaboratoryResultType::Numeric,
            'unit' => '10^9/L',
            'is_required' => true,
            'show_on_report' => true,
        ], $admin);

        return $test->refresh();
    }

    private function createLaboratoryQueue(LaboratoryOrder $order, User $admin): PatientQueue
    {
        $laboratory = Department::query()->forCurrentFacility()->where('code', 'LAB')->firstOrFail();
        $queue = PatientQueue::query()->create([
            'facility_id' => currentFacility()->id,
            'visit_id' => $order->visit_id,
            'patient_id' => $order->patient_id,
            'department_id' => $laboratory->id,
            'queue_number' => 'LAB-TEST-'.fake()->unique()->numerify('######'),
            'queue_date' => today(),
            'queue_status' => 'waiting',
            'priority' => 'normal',
            'position' => 1,
            'checked_in_at' => now(),
            'created_by' => $admin->id,
        ]);
        $order->visit->update([
            'visit_status' => 'awaiting_lab',
            'current_department_id' => $laboratory->id,
            'current_queue_id' => $queue->id,
        ]);

        return $queue;
    }

    private function submitResult(
        LaboratoryOrderItem $item,
        LaboratoryTest $test,
        float|int $value,
        User $admin,
    ): LaboratoryResult {
        $result = app(LaboratoryResultService::class)->createDraft($item, $admin);
        $parameter = $test->parameters()->firstOrFail();

        return app(LaboratoryResultService::class)->saveValues($result, [
            (string) $parameter->id => ['value' => $value],
        ], $admin, true);
    }

    private function activityCount(string $event, int $subjectId): int
    {
        return ActivityLog::query()
            ->where('event', $event)
            ->where('subject_id', $subjectId)
            ->count();
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
