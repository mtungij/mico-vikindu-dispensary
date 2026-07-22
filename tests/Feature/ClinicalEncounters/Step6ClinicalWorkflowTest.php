<?php

namespace Tests\Feature\ClinicalEncounters;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Livewire\Clinical\Icd10Search;
use App\Livewire\Opd\Consultation as OpdConsultation;
use App\Livewire\Opd\Queue as OpdQueue;
use App\Livewire\Triage\Assessment as TriageAssessmentComponent;
use App\Livewire\Triage\Queue as TriageQueue;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Icd10Code;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryResultValue;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestCategory;
use App\Models\Medicine;
use App\Models\MedicineUnit;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\SpecimenType;
use App\Models\StaffProfile;
use App\Models\TriageAssessment;
use App\Models\User;
use App\Models\Visit;
use App\Services\ClinicalEncounterService;
use App\Services\DiagnosisService;
use App\Services\PaymentConfirmationService;
use App\Services\TriageService;
use App\Services\VitalSignAssessmentService;
use App\Services\WorkflowService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\MinimalIcd10Seeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Step6ClinicalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_triage(): void
    {
        $this->get(route('triage.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_access_triage_and_opd_queues(): void
    {
        $admin = $this->bootstrappedFacility();
        Livewire::actingAs($admin)->test(TriageQueue::class)->assertOk();
        Livewire::actingAs($admin)->test(OpdQueue::class)->assertOk();
    }

    public function test_assigned_opd_provider_can_open_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin, VisitStatus::InConsultation);

        ClinicalEncounter::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->id,
            'department_id' => $visit->current_department_id,
            'encounter_type' => 'opd',
            'encounter_number' => 'ENC-TEST-001',
            'provider_user_id' => $doctor->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'created_by' => $doctor->id,
        ]);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertOk();
    }

    public function test_authorized_opd_provider_from_same_facility_can_open_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))
            ->assertOk()
            ->assertSee('Visit Information')
            ->assertSee('Payment / Insurance')
            ->assertDontSee('Doctor Notes')
            ->assertDontSee('wire:model.live.debounce.2000ms="form.clinical_summary"', false);

        $this->assertDatabaseHas('clinical_encounters', [
            'visit_id' => $visit->id,
            'department_id' => $visit->current_department_id,
            'provider_user_id' => $doctor->id,
        ]);
    }

    public function test_cashier_cannot_open_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $cashier = $this->staffUser('cashier');
        $visit = $this->opdVisit($admin);

        $this->actingAs($cashier)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_receptionist_cannot_open_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $receptionist = $this->staffUser('receptionist');
        $visit = $this->opdVisit($admin);

        $this->actingAs($receptionist)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_cross_facility_user_receives_403_for_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $otherFacility = Facility::query()->create([
            'name' => 'Other Dispensary',
            'code' => 'OTH',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000001',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'ward' => 'Upanga',
            'physical_address' => 'Upanga',
            'setup_completed_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $doctor = $this->staffUser('doctor', $otherFacility);
        $visit = $this->opdVisit($admin);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_patient_routed_to_opd_after_full_payment_can_be_opened(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $opd->update(['requires_triage' => false, 'queue_enabled' => true]);
        $invoice = $this->cashInvoiceForBillingVisit($admin, $opd, 10000);
        $cash = PaymentMethod::query()->create(['name' => 'Cash', 'code' => 'CASH_TEST', 'type' => 'cash', 'is_active' => true]);

        app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, 10000, $admin);

        $visit = $invoice->visit->refresh();
        $this->assertSame(VisitStatus::InProgress, $visit->visit_status);
        $this->assertSame($opd->id, $visit->current_department_id);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertOk();
    }

    public function test_visit_still_in_billing_cannot_open_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $billing = Department::query()->forCurrentFacility()->where('code', 'BIL')->firstOrFail();
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $visit = $this->visitInDepartment($admin, $billing, $opd, VisitStatus::Waiting);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_opd_summary_is_read_only_and_doctor_notes_are_in_plan_tab(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->assertSee('Patient Demographics')
            ->assertSee('Visit Information')
            ->assertSee('Latest Triage Vitals')
            ->assertSee('Payment / Insurance')
            ->assertDontSee('Doctor Plan')
            ->assertDontSee('wire:model.live.debounce.2000ms="form.clinical_summary"', false)
            ->set('activeTab', 'plan')
            ->assertSee('Doctor Plan')
            ->assertSee('Doctor notes / clinical summary');
    }

    public function test_orders_tab_separates_lab_catalogue_from_ordered_laboratory_tests(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $labService = $this->service('Full Blood Picture', 'FBP', 'laboratory_test', $admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);

        app(ClinicalEncounterService::class)->addLabOrder($encounter->refresh(), [
            'service_ids' => [$labService->id],
            'clinical_notes' => 'Rule out infection',
        ], $doctor);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit->refresh()])
            ->set('activeTab', 'orders')
            ->assertSee('Laboratory Test Catalogue')
            ->assertSee('Available Tests')
            ->assertSee('Ordered Laboratory Tests')
            ->assertSee('Full Blood Picture');
    }

    public function test_opd_displays_released_single_value_with_verification_and_release_metadata(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        [$visit, $result] = $this->laboratoryResultFixture($admin, $doctor, 'released', [
            ['parameter' => 'HIV Result', 'type' => 'reactive_non_reactive', 'selected_value' => 'non_reactive', 'flag' => 'normal'],
        ], 'HIV Rapid Test');

        $component = Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertSee('Released')
            ->assertSee('Non-Reactive')
            ->assertSee('Laboratory remarks')
            ->assertSee('Reviewed by laboratory')
            ->assertSee('Verified by')
            ->assertSee($admin->name)
            ->assertSee($result->verified_at->format('d/m/Y H:i'))
            ->assertSee('Released at')
            ->assertSee($result->released_at->format('d/m/Y H:i'));

        $loadedItem = $component->get('encounter')->laboratoryOrders->first()->items->first();
        $this->assertTrue($loadedItem->relationLoaded('results'));
        $this->assertTrue($loadedItem->results->first()->relationLoaded('values'));
        $this->assertTrue($loadedItem->results->first()->relationLoaded('verifier'));
        $this->assertTrue($loadedItem->results->first()->relationLoaded('releaser'));
    }

    public function test_opd_displays_every_verified_parameter_unit_range_and_abnormal_flag(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        [$visit] = $this->laboratoryResultFixture($admin, $doctor, 'verified', [
            ['parameter' => 'Haemoglobin', 'type' => 'numeric', 'numeric_value' => 7.5, 'unit' => 'g/dL', 'range' => '12 - 16 g/dL', 'flag' => 'low'],
            ['parameter' => 'White Blood Cells', 'type' => 'numeric', 'numeric_value' => 18.2, 'unit' => '10^9/L', 'range' => '4 - 11 10^9/L', 'flag' => 'critical_high', 'critical' => true],
        ], 'Full Blood Picture');

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'results')
            ->assertSee('Verified')
            ->assertSee('Haemoglobin')
            ->assertSee('7.5')
            ->assertSee('g/dL')
            ->assertSee('12 - 16 g/dL')
            ->assertSee('Low')
            ->assertSee('White Blood Cells')
            ->assertSee('18.2')
            ->assertSee('10^9/L')
            ->assertSee('Critical High');
    }

    public function test_opd_hides_pending_verification_values_and_uses_workflow_status(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        [$visit] = $this->laboratoryResultFixture($admin, $doctor, 'pending_verification', [
            ['parameter' => 'Confidential Result', 'type' => 'text', 'text_value' => 'SECRET-PENDING-VALUE', 'flag' => 'normal'],
        ], 'Pending Test');

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertSee('Awaiting Verification')
            ->assertDontSee('SECRET-PENDING-VALUE')
            ->assertDontSee('Result Ready');
    }

    public function test_opd_hides_cross_facility_results(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        [$visit, $result] = $this->laboratoryResultFixture($admin, $doctor, 'verified', [
            ['parameter' => 'Private Result', 'type' => 'text', 'text_value' => 'FACILITY-SECRET', 'flag' => 'normal'],
        ], 'Facility Test');
        $foreignFacility = Facility::factory()->create(['created_by' => $admin->id, 'updated_by' => $admin->id]);
        $result->update(['facility_id' => $foreignFacility->id]);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertDontSee('FACILITY-SECRET');
    }

    public function test_opd_hides_result_values_from_doctor_without_permission(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        [$visit] = $this->laboratoryResultFixture($admin, $doctor, 'verified', [
            ['parameter' => 'Private Result', 'type' => 'text', 'text_value' => 'PERMISSION-SECRET', 'flag' => 'normal'],
        ], 'Permission Test');
        $restrictedDoctor = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => currentFacility()->id, 'user_id' => $restrictedDoctor->id]);
        $restrictedDoctor->givePermissionTo(['opd.consult', 'opd.view-clinical-history']);

        Livewire::actingAs($restrictedDoctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertSee('Huna ruhusa ya kuona matokeo ya maabara.')
            ->assertDontSee('PERMISSION-SECRET');
    }

    public function test_result_ready_order_without_saved_result_is_shown_as_processing(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);
        $service = $this->service('No Saved Result', 'NO-RESULT', 'laboratory_test', $admin);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter->refresh(), ['service_ids' => [$service->id]], $doctor);
        $order->update(['status' => 'result_ready']);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertSee('In Processing')
            ->assertDontSee('Result Ready');
    }

    public function test_orders_tab_uses_medicine_catalogue_and_contains_referral_orders(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $medicine = $this->medicine($admin);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertSee('Medication Orders')
            ->assertSee('Select medicine')
            ->assertSee($medicine->name)
            ->assertSee('Referral Orders')
            ->set('activeTab', 'follow')
            ->assertSee('Follow-up Appointment')
            ->assertDontSee('Referral Orders');
    }

    public function test_doctor_and_clinical_officer_can_submit_selected_laboratory_tests(): void
    {
        $admin = $this->bootstrappedFacility();

        foreach (['doctor', 'clinical-officer'] as $index => $role) {
            $clinician = $this->staffUser($role);
            $visit = $this->opdVisit($admin);
            $labService = $this->service("Laboratory Test {$index}", "LAB-AUTH-{$index}", 'laboratory_test', $admin);

            Livewire::actingAs($clinician)
                ->test(OpdConsultation::class, ['visit' => $visit])
                ->set('labForm.service_ids', [$labService->id])
                ->set('labForm.clinical_notes', 'Clinical indication')
                ->call('addLabOrder')
                ->assertHasNoErrors();

            $this->assertDatabaseHas('laboratory_orders', [
                'visit_id' => $visit->id,
                'ordered_by' => $clinician->id,
            ]);
            $this->assertDatabaseHas('laboratory_order_items', [
                'service_id' => $labService->id,
            ]);
            $this->assertDatabaseHas('invoice_items', [
                'visit_id' => $visit->id,
                'service_id' => $labService->id,
            ]);
        }
    }

    public function test_lab_order_role_permissions_follow_clinical_separation_of_duties(): void
    {
        $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $clinicalOfficer = $this->staffUser('clinical-officer');
        $receptionist = $this->staffUser('receptionist');
        $cashier = $this->staffUser('cashier');
        $laboratoryTechnician = $this->staffUser('laboratory-technician');

        foreach ([$doctor, $clinicalOfficer] as $clinician) {
            $this->assertTrue($clinician->can('opd.consult'));
            $this->assertTrue($clinician->can('diagnoses.create'));
            $this->assertTrue($clinician->can('laboratory-orders.create'));
            $this->assertTrue($clinician->can('laboratory-orders.view'));
            $this->assertFalse($clinician->can('services.view'));
            $this->assertTrue($clinician->can('laboratory-results.release'));
        }

        $this->assertFalse($receptionist->can('laboratory-orders.create'));
        $this->assertFalse($cashier->can('laboratory-orders.create'));
        $this->assertFalse($laboratoryTechnician->can('laboratory-orders.create'));
        $this->assertTrue($laboratoryTechnician->can('laboratory-orders.view'));
        $this->assertTrue($laboratoryTechnician->can('laboratory.receive-sample'));
        $this->assertTrue($laboratoryTechnician->can('laboratory-results.enter'));
        $this->assertTrue($laboratoryTechnician->can('laboratory-results.verify'));
        $this->assertTrue($laboratoryTechnician->can('laboratory-results.release'));
    }

    public function test_opd_user_without_lab_order_permission_gets_an_inline_error_and_no_partial_order(): void
    {
        $admin = $this->bootstrappedFacility();
        $user = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => currentFacility()->id, 'user_id' => $user->id]);
        $user->givePermissionTo(['opd.consult', 'opd.view-clinical-history']);
        $visit = $this->opdVisit($admin);
        $labService = $this->service('Unauthorized Test', 'LAB-NO-AUTH', 'laboratory_test', $admin);

        Livewire::actingAs($user)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('labForm.service_ids', [$labService->id])
            ->call('addLabOrder')
            ->assertHasErrors(['labForm.service_ids']);

        $this->assertDatabaseMissing('laboratory_orders', ['visit_id' => $visit->id]);
        $this->assertDatabaseMissing('invoice_items', ['visit_id' => $visit->id, 'service_id' => $labService->id]);
    }

    public function test_cross_facility_doctor_cannot_create_an_opd_laboratory_order(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $otherFacility = Facility::query()->create([
            'name' => 'Other Laboratory Facility',
            'code' => 'OLF',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000002',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'ward' => 'Upanga',
            'physical_address' => 'Upanga',
            'setup_completed_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $otherDoctor = $this->staffUser('doctor', $otherFacility);
        $labService = $this->service('Facility Scoped Test', 'LAB-SCOPE', 'laboratory_test', $admin);

        try {
            app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$labService->id]], $otherDoctor);
            $this->fail('A cross-facility clinician was allowed to create a laboratory order.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('laboratory_orders', ['visit_id' => $visit->id]);
        }
    }

    public function test_doctor_cannot_order_laboratory_tests_for_a_completed_encounter(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);
        $encounter->update(['status' => 'completed', 'completed_at' => now()]);
        $labService = $this->service('Completed Visit Test', 'LAB-COMPLETE', 'laboratory_test', $admin);

        try {
            app(ClinicalEncounterService::class)->addLabOrder($encounter->refresh(), ['service_ids' => [$labService->id]], $doctor);
            $this->fail('A completed encounter was allowed to receive a laboratory order.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Laboratory orders cannot be added because this consultation is already completed.',
                $exception->errors()['encounter'][0],
            );
            $this->assertDatabaseMissing('laboratory_orders', ['visit_id' => $visit->id]);
        }
    }

    public function test_inactive_or_cross_facility_services_cannot_create_partial_lab_orders(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);
        $validService = $this->service('Valid Laboratory Test', 'LAB-VALID', 'laboratory_test', $admin);
        $inactiveService = $this->service('Inactive Laboratory Test', 'LAB-INACTIVE', 'laboratory_test', $admin);
        $inactiveService->update(['is_active' => false]);

        try {
            app(ClinicalEncounterService::class)->addLabOrder($encounter, [
                'service_ids' => [$validService->id, $inactiveService->id],
            ], $doctor);
            $this->fail('An inactive service was allowed into a laboratory order.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('laboratory_orders', ['visit_id' => $visit->id]);
            $this->assertDatabaseMissing('invoice_items', ['visit_id' => $visit->id, 'service_id' => $validService->id]);
        }

        $otherFacility = Facility::query()->create([
            'name' => 'External Service Facility',
            'code' => 'ESF',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000003',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'ward' => 'Upanga',
            'physical_address' => 'Upanga',
            'setup_completed_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $foreignService = Service::query()->create([
            'facility_id' => $otherFacility->id,
            'service_category_id' => $validService->service_category_id,
            'name' => 'Foreign Laboratory Test',
            'code' => 'LAB-FOREIGN',
            'service_type' => 'laboratory_test',
            'requires_payment' => true,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        try {
            app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$foreignService->id]], $doctor);
            $this->fail('A cross-facility service was allowed into a laboratory order.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('laboratory_orders', ['visit_id' => $visit->id]);
            $this->assertDatabaseMissing('invoice_items', ['visit_id' => $visit->id, 'service_id' => $foreignService->id]);
        }
    }

    public function test_laboratory_order_policy_requires_an_active_opd_encounter(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);

        $this->assertTrue(Gate::forUser($doctor)->allows('create', [LaboratoryOrder::class, $encounter]));

        $encounter->update(['encounter_type' => 'dental']);

        $this->assertFalse(Gate::forUser($doctor)->allows('create', [LaboratoryOrder::class, $encounter->refresh()]));
    }

    public function test_adding_lab_order_keeps_encounter_in_consultation_until_completion(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $labService = $this->service('Malaria MRDT', 'MRDT', 'laboratory_test', $admin);

        app(ClinicalEncounterService::class)->addLabOrder($encounter->refresh(), [
            'service_ids' => [$labService->id],
            'clinical_notes' => 'Fever',
        ], $admin);

        $this->assertSame('in_progress', $encounter->refresh()->status->value);
        $this->assertSame(VisitStatus::InConsultation, $visit->refresh()->visit_status);
    }

    public function test_missing_opd_consult_permission_returns_403(): void
    {
        $admin = $this->bootstrappedFacility();
        $user = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => currentFacility()->id, 'user_id' => $user->id]);
        $user->givePermissionTo('opd.view-queue');
        $visit = $this->opdVisit($admin);

        $this->actingAs($user)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_icd10_search_matches_code_description_and_keywords_without_loading_on_empty_query(): void
    {
        Icd10Code::factory()->create([
            'code' => 'J18.9',
            'title' => 'Pneumonia, unspecified organism',
            'description' => 'Acute infection of the lung',
            'metadata' => ['keywords' => ['chest infection']],
            'is_active' => true,
        ]);
        Icd10Code::factory()->create([
            'code' => 'I10',
            'title' => 'Essential hypertension',
            'description' => 'High blood pressure',
            'is_active' => true,
        ]);

        Livewire::test(Icd10Search::class)
            ->assertDontSee('J18.9')
            ->set('query', 'J18')
            ->assertSee('J18.9')
            ->set('query', 'blood pressure')
            ->assertSee('Essential hypertension')
            ->set('query', 'chest infection')
            ->assertSee('Pneumonia, unspecified organism');
    }

    public function test_icd10_exact_code_and_prefix_results_are_ranked_first(): void
    {
        Icd10Code::factory()->create(['code' => 'AJ18', 'title' => 'Mentions J18', 'is_active' => true]);
        Icd10Code::factory()->create(['code' => 'J18.9', 'title' => 'Pneumonia', 'is_active' => true]);
        Icd10Code::factory()->create(['code' => 'J18', 'title' => 'Pneumonia exact category', 'is_active' => true]);

        $codes = Icd10Code::query()->search('j18')->pluck('code')->all();

        $this->assertSame(['J18', 'J18.9', 'AJ18'], $codes);
    }

    public function test_selecting_icd10_result_dispatches_values_and_fills_diagnosis_form(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $code = Icd10Code::query()->where('code', 'B54')->firstOrFail();

        Livewire::test(Icd10Search::class)
            ->set('query', 'malaria')
            ->call('selectCode', $code->id)
            ->assertSet('query', 'B54 — Unspecified malaria')
            ->assertSet('showResults', false)
            ->assertDispatched('icd10-selected', code: 'B54', title: 'Unspecified malaria');

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->dispatch('icd10-selected', code: 'B54', title: 'Unspecified malaria')
            ->assertSet('diagnosisForm.icd10_code', 'B54')
            ->assertSet('diagnosisForm.diagnosis_name', 'Unspecified malaria');
    }

    public function test_doctor_can_save_selected_icd10_diagnosis_and_primary_logic_is_preserved(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $component = Livewire::actingAs($doctor)->test(OpdConsultation::class, ['visit' => $visit]);

        $component
            ->dispatch('icd10-selected', code: 'B54', title: 'Unspecified malaria')
            ->set('diagnosisForm.diagnosis_type', 'provisional')
            ->set('diagnosisForm.certainty', 'probable')
            ->set('diagnosisForm.is_primary', true)
            ->call('addDiagnosis')
            ->assertHasNoErrors();

        $component
            ->dispatch('icd10-selected', code: 'I10', title: 'Essential hypertension')
            ->set('diagnosisForm.diagnosis_type', 'confirmed')
            ->set('diagnosisForm.certainty', 'confirmed')
            ->set('diagnosisForm.is_primary', true)
            ->call('addDiagnosis')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('diagnoses', [
            'visit_id' => $visit->id,
            'icd10_code' => 'I10',
            'diagnosis_name' => 'Essential hypertension',
            'diagnosis_type' => 'confirmed',
            'certainty' => 'confirmed',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('diagnoses', [
            'visit_id' => $visit->id,
            'icd10_code' => 'B54',
            'is_primary' => false,
        ]);

        $component
            ->set('diagnosisForm.icd10_code', null)
            ->set('diagnosisForm.diagnosis_name', 'Manual clinical diagnosis')
            ->set('diagnosisForm.diagnosis_type', 'provisional')
            ->set('diagnosisForm.certainty', 'suspected')
            ->set('diagnosisForm.is_primary', false)
            ->assertSet('icd10Selected', false)
            ->call('addDiagnosis')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('diagnoses', [
            'visit_id' => $visit->id,
            'icd10_code' => null,
            'diagnosis_name' => 'Manual clinical diagnosis',
        ]);
    }

    public function test_user_without_diagnosis_create_permission_cannot_add_diagnosis(): void
    {
        $admin = $this->bootstrappedFacility();
        $user = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => currentFacility()->id, 'user_id' => $user->id]);
        $user->givePermissionTo('opd.consult');
        $this->opdVisit($admin);
        $this->actingAs($user);

        $this->expectException(AuthorizationException::class);

        app(OpdConsultation::class)->addDiagnosis(app(ClinicalEncounterService::class));
    }

    public function test_diagnoses_tab_renders_search_dropdown_and_empty_catalogue_message(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'diagnoses')
            ->assertSee('Search ICD-10 code or diagnosis...')
            ->assertSeeLivewire(Icd10Search::class);

        Icd10Code::query()->delete();

        Livewire::test(Icd10Search::class)
            ->assertSee('No ICD-10 codes are available. Ask the administrator to import the ICD-10 catalogue.');
    }

    public function test_triage_assessment_calculates_bmi_creates_alert_and_moves_visit_to_opd_queue(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);

        $assessment = app(TriageService::class)->startAssessment($visit, $admin);
        $assessment = app(TriageService::class)->completeAssessment($assessment, [
            'triage_level' => 'urgent',
            'chief_complaint_summary' => 'Fever and shortness of breath',
            'temperature' => 39.8,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'pulse_rate' => 110,
            'oxygen_saturation' => 88,
            'weight_kg' => 80,
            'height_cm' => 180,
            'pain_score' => 7,
            'danger_signs' => [],
        ], $admin);

        $this->assertSame('24.69', $assessment->bmi);
        $this->assertDatabaseHas('clinical_alerts', ['visit_id' => $visit->id, 'alert_type' => 'abnormal_vital']);
        $this->assertSame(VisitStatus::InQueue, $visit->refresh()->visit_status);
        $this->assertDatabaseHas('patient_queues', ['visit_id' => $visit->id, 'queue_status' => 'waiting']);
    }

    public function test_triage_form_hydrates_enum_casts_as_select_values(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $assessment = app(TriageService::class)->startAssessment($visit, $admin);
        $assessment->update([
            'triage_level' => 'urgent',
            'consciousness_level' => 'alert',
            'pregnancy_status' => 'not_applicable',
        ]);

        Livewire::actingAs($admin)
            ->test(TriageAssessmentComponent::class, ['visit' => $visit])
            ->assertSet('form.triage_level', 'urgent')
            ->assertSet('form.consciousness_level', 'alert')
            ->assertSet('form.pregnancy_status', 'not_applicable')
            ->assertSet('form.danger_signs', [])
            ->assertSet('form.allergies_confirmed', false);
    }

    public function test_nurse_can_open_triage_page(): void
    {
        $admin = $this->bootstrappedFacility();
        $nurse = $this->staffUser('nurse');
        $visit = $this->visit($admin);

        Livewire::actingAs($nurse)
            ->test(TriageAssessmentComponent::class, ['visit' => $visit])
            ->assertOk()
            ->assertSee('Kamilisha Triage');
    }

    public function test_completion_shows_inline_errors_preserves_values_and_dispatches_first_invalid_field(): void
    {
        $admin = $this->bootstrappedFacility();
        $nurse = $this->staffUser('nurse');
        $visit = $this->visit($admin);

        Livewire::actingAs($nurse)
            ->test(TriageAssessmentComponent::class, ['visit' => $visit])
            ->set('form.chief_complaint_summary', 'Persistent fever')
            ->set('form.temperature', 'invalid')
            ->call('complete')
            ->assertHasErrors([
                'form.temperature' => 'numeric',
                'form.systolic_bp' => 'required',
                'form.diastolic_bp' => 'required',
                'form.pulse_rate' => 'required',
                'form.respiratory_rate' => 'required',
                'form.oxygen_saturation' => 'required',
                'form.pain_score' => 'required',
                'form.consciousness_level' => 'required',
                'form.allergies_confirmed' => 'accepted',
            ])
            ->assertSet('form.chief_complaint_summary', 'Persistent fever')
            ->assertSet('form.temperature', 'invalid')
            ->assertDispatched('triage-validation-failed')
            ->assertSee('Hatukuweza kukamilisha Triage.')
            ->assertSee('Joto la mwili lazima liwe namba.');

        $this->assertDatabaseHas('triage_assessments', [
            'visit_id' => $visit->id,
            'status' => 'draft',
        ]);
    }

    public function test_valid_triage_completion_records_completion_and_moves_patient_queue(): void
    {
        $admin = $this->bootstrappedFacility();
        $nurse = $this->staffUser('nurse');
        $visit = $this->visit($admin);
        $component = Livewire::actingAs($nurse)->test(TriageAssessmentComponent::class, ['visit' => $visit]);

        foreach ($this->validTriageData() as $field => $value) {
            $component->set("form.{$field}", $value);
        }

        $component->call('complete')->assertRedirect(route('triage.index'));

        $assessment = TriageAssessment::query()->where('visit_id', $visit->id)->firstOrFail();
        $this->assertSame('completed', $assessment->status->value);
        $this->assertSame($nurse->id, $assessment->completed_by);
        $this->assertNotNull($assessment->completed_at);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'triage_completed',
            'subject_id' => $assessment->id,
        ]);
        $this->assertDatabaseHas('patient_queues', [
            'visit_id' => $visit->id,
            'queue_status' => 'waiting',
        ]);
    }

    public function test_unauthorized_user_cannot_complete_triage(): void
    {
        $admin = $this->bootstrappedFacility();
        $user = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => currentFacility()->id, 'user_id' => $user->id]);
        $user->givePermissionTo('triage.record-vitals');
        $visit = $this->visit($admin);
        $component = Livewire::actingAs($user)->test(TriageAssessmentComponent::class, ['visit' => $visit]);

        foreach ($this->validTriageData() as $field => $value) {
            $component->set("form.{$field}", $value);
        }

        $component->call('complete')->assertNoRedirect();

        $this->assertDatabaseHas('triage_assessments', [
            'visit_id' => $visit->id,
            'status' => 'draft',
            'completed_by' => null,
        ]);
    }

    public function test_completed_triage_cannot_be_completed_twice(): void
    {
        $admin = $this->bootstrappedFacility();
        $assessment = app(TriageService::class)->startAssessment($this->visit($admin), $admin);
        app(TriageService::class)->completeAssessment($assessment, $this->validTriageData(), $admin);
        $queueCount = PatientQueue::query()->count();

        $this->expectException(ValidationException::class);

        try {
            app(TriageService::class)->completeAssessment($assessment->refresh(), $this->validTriageData(), $admin);
        } finally {
            $this->assertSame($queueCount, PatientQueue::query()->count());
        }
    }

    public function test_incomplete_triage_can_be_saved_as_draft(): void
    {
        $admin = $this->bootstrappedFacility();
        $nurse = $this->staffUser('nurse');
        $visit = $this->visit($admin);

        Livewire::actingAs($nurse)
            ->test(TriageAssessmentComponent::class, ['visit' => $visit])
            ->set('form.chief_complaint_summary', 'Assessment is still in progress')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertDatabaseHas('triage_assessments', [
            'visit_id' => $visit->id,
            'chief_complaint_summary' => 'Assessment is still in progress',
            'status' => 'draft',
            'completed_at' => null,
        ]);
    }

    public function test_cross_facility_triage_page_is_rejected(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $otherFacility = Facility::query()->create([
            'name' => 'Other Triage Facility',
            'code' => 'TRI-OTHER',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000099',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'ward' => 'Upanga',
            'physical_address' => 'Upanga',
            'setup_completed_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $visit->update(['facility_id' => $otherFacility->id]);

        $this->actingAs($admin)
            ->get(route('triage.assessment', $visit))
            ->assertNotFound();
    }

    public function test_database_failure_rolls_back_triage_completion_and_workflow(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $assessment = app(TriageService::class)->startAssessment($visit, $admin);
        $workflow = $this->mock(WorkflowService::class);
        $workflow->shouldReceive('transferPatient')->once()->andThrow(new \RuntimeException('Simulated workflow failure'));

        try {
            app(TriageService::class)->completeAssessment($assessment, $this->validTriageData(), $admin);
            $this->fail('The simulated workflow failure was not raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated workflow failure', $exception->getMessage());
        }

        $assessment->refresh();
        $this->assertSame('draft', $assessment->status->value);
        $this->assertNull($assessment->completed_by);
        $this->assertNull($assessment->completed_at);
        $this->assertDatabaseMissing('activity_logs', [
            'event' => 'triage_completed',
            'subject_id' => $assessment->id,
        ]);
    }

    public function test_completed_triage_leaves_triage_and_enters_opd_queue_for_immediate_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $nurse = $this->staffUser('nurse');
        $doctor = $this->staffUser('doctor');
        $triage = Department::query()->forCurrentFacility()->where('code', 'TRI')->firstOrFail();
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $triage->update(['queue_enabled' => true]);
        $opd->update(['queue_enabled' => true, 'requires_triage' => true]);
        $visit = $this->visitInDepartment($admin, $triage, $opd, VisitStatus::AwaitingTriage);
        $triageQueue = app(WorkflowService::class)->createQueue(
            $visit,
            $triage,
            $admin,
            VisitStatus::AwaitingTriage,
            'Triage required before OPD'
        );
        $assessment = app(TriageService::class)->startAssessment($visit->refresh(), $nurse);

        app(TriageService::class)->completeAssessment($assessment, $this->validTriageData(), $nurse);

        $visit->refresh();
        $this->assertSame(VisitStatus::InQueue, $visit->visit_status);
        $this->assertSame($opd->id, $visit->current_department_id);
        $this->assertSame($opd->id, $visit->destination_department_id);
        $this->assertSame('completed', $triageQueue->refresh()->queue_status->value);
        $this->assertDatabaseHas('patient_queues', [
            'visit_id' => $visit->id,
            'department_id' => $opd->id,
            'queue_status' => 'waiting',
        ]);
        $this->assertSame(1, PatientQueue::query()
            ->where('visit_id', $visit->id)
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->count());
        $this->assertDatabaseMissing('patient_queues', [
            'visit_id' => $visit->id,
            'department_id' => $triage->id,
            'queue_status' => 'waiting',
        ]);

        Livewire::actingAs($nurse)
            ->test(TriageQueue::class)
            ->assertDontSee($visit->patient->patient_number);
        Livewire::actingAs($doctor)
            ->test(OpdQueue::class)
            ->assertSee($visit->patient->patient_number);
        $this->actingAs($doctor)
            ->get(route('opd.consultation', $visit))
            ->assertOk();
    }

    public function test_legacy_triage_destination_is_repaired_to_opd_on_completion(): void
    {
        $admin = $this->bootstrappedFacility();
        $triage = Department::query()->forCurrentFacility()->where('code', 'TRI')->firstOrFail();
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $visit = $this->visitInDepartment($admin, $triage, $triage, VisitStatus::AwaitingTriage);
        $queue = app(WorkflowService::class)->createQueue($visit, $triage, $admin, VisitStatus::AwaitingTriage, 'Legacy triage queue');
        $assessment = app(TriageService::class)->startAssessment($visit->refresh(), $admin);

        app(TriageService::class)->completeAssessment($assessment, $this->validTriageData(), $admin);

        $visit->refresh();
        $this->assertSame($opd->id, $visit->destination_department_id);
        $this->assertSame($opd->id, $visit->current_department_id);
        $this->assertSame('completed', $queue->refresh()->queue_status->value);
        $this->assertDatabaseHas('patient_queues', [
            'visit_id' => $visit->id,
            'department_id' => $opd->id,
            'queue_status' => 'waiting',
        ]);
    }

    public function test_invalid_pain_score_and_oxygen_saturation_are_rejected(): void
    {
        $this->bootstrappedFacility();
        $this->expectException(ValidationException::class);
        app(VitalSignAssessmentService::class)->validateVitalRanges(['pain_score' => 11, 'oxygen_saturation' => 101]);
    }

    public function test_clinician_can_start_save_signoff_and_complete_encounter(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, VisitStatus::InQueue);
        $service = app(ClinicalEncounterService::class);

        $encounter = $service->startEncounter($visit, $admin);
        $service->saveDraft($encounter, ['clinical_summary' => 'Stable patient', 'treatment_plan' => 'Oral medication', 'outcome' => 'discharged_home'], $admin);
        $service->addDiagnosis($encounter->refresh(), ['diagnosis_type' => 'final', 'diagnosis_name' => 'Fever', 'certainty' => 'confirmed', 'is_primary' => true], $admin);
        $service->signOff($encounter->refresh(), $admin);
        $completed = $service->completeEncounter($encounter->refresh(), $admin);

        $this->assertSame('completed', $completed->status->value);
        $this->assertSame(VisitStatus::Completed, $visit->refresh()->visit_status);
    }

    public function test_two_clinicians_cannot_start_same_department_encounter(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, VisitStatus::InQueue);
        app(ClinicalEncounterService::class)->startEncounter($visit, $admin);

        $this->expectException(ValidationException::class);
        app(ClinicalEncounterService::class)->startEncounter($visit->refresh(), User::factory()->create());
    }

    public function test_completed_encounter_is_immutable_without_amend_permission(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, VisitStatus::InQueue);
        $service = app(ClinicalEncounterService::class);
        $encounter = $service->startEncounter($visit, $admin);
        $service->saveDraft($encounter, ['clinical_summary' => 'Summary', 'treatment_plan' => 'Plan', 'outcome' => 'discharged_home'], $admin);
        $service->addDiagnosis($encounter->refresh(), ['diagnosis_type' => 'final', 'diagnosis_name' => 'Fever', 'certainty' => 'confirmed'], $admin);
        $service->signOff($encounter->refresh(), $admin);
        $completed = $service->completeEncounter($encounter->refresh(), $admin);

        $user = User::factory()->create();
        $this->expectException(ValidationException::class);
        $service->saveDraft($completed, ['clinical_summary' => 'Changed'], $user);
    }

    public function test_diagnosis_primary_is_unique_and_icd_import_is_idempotent(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = app(ClinicalEncounterService::class)->startEncounter($this->visit($admin, VisitStatus::InQueue), $admin);
        $service = app(DiagnosisService::class);
        $first = $service->addDiagnosis($encounter, ['diagnosis_type' => 'provisional', 'diagnosis_name' => 'Malaria', 'certainty' => 'probable', 'is_primary' => true], $admin);
        $second = $service->addDiagnosis($encounter, ['diagnosis_type' => 'final', 'diagnosis_name' => 'Fever', 'certainty' => 'confirmed', 'is_primary' => true], $admin);

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);

        $path = tempnam(sys_get_temp_dir(), 'icd');
        file_put_contents($path, "code,title\nZ99,Test code\nZ99,Test code updated\n");
        Artisan::call('icd10:import', ['file' => $path]);
        Artisan::call('icd10:import', ['file' => $path]);
        $this->assertSame(1, Icd10Code::query()->where('code', 'Z99')->count());
    }

    public function test_lab_order_prescription_procedure_followup_and_referral_foundations_work(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $labService = $this->service('Malaria MRDT', 'LAB001', 'laboratory_test', $admin);
        $procedure = $this->service('Dressing', 'PROC001', 'procedure', $admin);
        $clinical = app(ClinicalEncounterService::class);

        $lab = $clinical->addLabOrder($encounter->refresh(), ['service_ids' => [$labService->id], 'clinical_notes' => 'Rule out malaria'], $admin);
        $rx = $clinical->addPrescription($encounter->refresh(), ['items' => [['medication_name' => 'Paracetamol', 'dose' => '500mg', 'frequency' => 'TDS', 'duration_value' => 3, 'duration_unit' => 'days']]], $admin);
        $proc = $clinical->addProcedureOrder($encounter->refresh(), ['service_id' => $procedure->id, 'procedure_name_snapshot' => 'Dressing'], $admin);
        $appt = $clinical->createFollowUp($encounter->refresh(), ['scheduled_start' => now()->addDay()->format('Y-m-d H:i:s'), 'department_id' => $encounter->department_id], $admin);
        $ref = $clinical->createReferral($encounter->refresh(), ['destination_facility_name' => 'Regional Hospital', 'reason' => 'Specialist review', 'urgency' => 'urgent'], $admin);

        $this->assertStringStartsWith('LAB-', $lab->order_number);
        $this->assertStringStartsWith('RX-', $rx->prescription_number);
        $this->assertSame('Dressing', $proc->procedure_name_snapshot);
        $this->assertSame('booked', $appt->status->value);
        $this->assertStringStartsWith('REF-', $ref->referral_number);
    }

    public function test_routes_for_prints_dashboard_and_reports_render(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = app(ClinicalEncounterService::class)->startEncounter($this->visit($admin, VisitStatus::InQueue), $admin);
        $referral = app(ClinicalEncounterService::class)->createReferral($encounter, ['destination_facility_name' => 'Regional Hospital', 'reason' => 'Review', 'urgency' => 'routine'], $admin);

        $this->actingAs($admin)->get(route('opd.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('clinical-encounters.print', $encounter))->assertOk();
        $this->actingAs($admin)->get(route('referrals.print', $referral))->assertOk();
        $this->actingAs($admin)->get(route('reports.triage.export'))->assertOk();
        $this->actingAs($admin)->get(route('reports.opd.export'))->assertOk();
        $this->actingAs($admin)->get(route('reports.diagnoses.export'))->assertOk();
        $this->actingAs($admin)->get(route('reports.referrals.export'))->assertOk();
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
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, MinimalIcd10Seeder::class, RoleSeeder::class, RolePermissionSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    private function staffUser(string $roleName, ?Facility $facility = null): User
    {
        $user = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => ($facility ?? currentFacility())->id, 'user_id' => $user->id]);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->assignRole($role);

        return $user;
    }

    private function patient(User $admin): Patient
    {
        return Patient::query()->create(['facility_id' => currentFacility()->id, 'patient_number' => 'PAT-2026-'.fake()->unique()->numerify('######'), 'first_name' => 'Test', 'last_name' => 'Patient', 'gender' => 'male', 'age_years' => 30, 'patient_status' => 'active', 'created_by' => $admin->id, 'registered_at' => now()]);
    }

    private function visit(User $admin, VisitStatus $status = VisitStatus::AwaitingTriage): Visit
    {
        $department = Department::query()->forCurrentFacility()->firstOrFail();
        $department->update(['clinical_department' => true, 'queue_enabled' => true]);

        return Visit::query()->create(['facility_id' => currentFacility()->id, 'patient_id' => $this->patient($admin)->id, 'visit_number' => 'VIS-2026-'.fake()->unique()->numerify('######'), 'visit_type' => 'new_patient', 'payer_type' => 'insurance', 'destination_department_id' => $department->id, 'current_department_id' => $department->id, 'visit_status' => $status, 'priority' => 'normal', 'registered_at' => now(), 'created_by' => $admin->id]);
    }

    private function opdVisit(User $admin, VisitStatus $status = VisitStatus::InProgress): Visit
    {
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();

        return $this->visitInDepartment($admin, $opd, $opd, $status);
    }

    private function visitInDepartment(User $admin, Department $currentDepartment, Department $destination, VisitStatus $status): Visit
    {
        $patient = $this->patient($admin);
        $visit = Visit::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_number' => 'VIS-2026-'.fake()->unique()->numerify('######'),
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => $destination->id,
            'current_department_id' => $currentDepartment->id,
            'visit_status' => $status,
            'priority' => 'normal',
            'registered_at' => now(),
            'created_by' => $admin->id,
        ]);

        if ($currentDepartment->code === 'OPD') {
            $queue = PatientQueue::query()->create([
                'facility_id' => currentFacility()->id,
                'visit_id' => $visit->id,
                'patient_id' => $patient->id,
                'department_id' => $currentDepartment->id,
                'queue_number' => 'OPD-TST-'.fake()->unique()->numerify('###'),
                'queue_date' => today(),
                'queue_status' => $status === VisitStatus::InConsultation ? QueueStatus::Serving : QueueStatus::Waiting,
                'priority' => 'normal',
                'position' => 1,
                'checked_in_at' => now(),
                'created_by' => $admin->id,
            ]);
            $visit->update(['current_queue_id' => $queue->id]);
        }

        return $visit->refresh();
    }

    private function cashInvoiceForBillingVisit(User $admin, Department $destination, int $amount): Invoice
    {
        $billing = Department::query()->forCurrentFacility()->where('code', 'BIL')->firstOrFail();
        $patient = $this->patient($admin);
        $visit = Visit::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_number' => 'VIS-BIL-'.fake()->unique()->numerify('######'),
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => $destination->id,
            'current_department_id' => $billing->id,
            'visit_status' => VisitStatus::Waiting,
            'priority' => 'normal',
            'registered_at' => now(),
            'created_by' => $admin->id,
        ]);

        PatientQueue::query()->create([
            'facility_id' => currentFacility()->id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'department_id' => $billing->id,
            'queue_number' => 'BIL-TST-'.fake()->unique()->numerify('###'),
            'queue_date' => today(),
            'queue_status' => QueueStatus::Waiting,
            'priority' => 'normal',
            'position' => 1,
            'checked_in_at' => now(),
            'created_by' => $admin->id,
        ]);

        $invoice = Invoice::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'invoice_number' => 'INV-OPD-AUTH-'.fake()->unique()->numerify('######'),
            'payer_type' => 'cash',
            'invoice_status' => 'pending',
            'subtotal' => $amount,
            'patient_amount' => $amount,
            'total_amount' => $amount,
            'balance_amount' => $amount,
            'status' => 'open',
            'payment_status' => 'unpaid',
            'currency' => 'TZS',
            'issued_at' => now(),
            'created_by' => $admin->id,
        ]);

        InvoiceItem::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'item_type' => 'consultation',
            'description' => 'Consultation',
            'description_snapshot' => 'Consultation',
            'quantity' => 1,
            'unit_price' => $amount,
            'gross_amount' => $amount,
            'payer_amount' => $amount,
            'patient_amount' => $amount,
            'insurance_amount' => 0,
            'total_amount' => $amount,
            'net_amount' => $amount,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        return $invoice;
    }

    private function validTriageData(): array
    {
        return [
            'triage_level' => 'urgent',
            'chief_complaint_summary' => 'Fever and shortness of breath',
            'temperature' => '39.5',
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'pulse_rate' => 110,
            'respiratory_rate' => 24,
            'oxygen_saturation' => '94',
            'weight_kg' => '70',
            'height_cm' => '170',
            'blood_glucose' => null,
            'muac_cm' => null,
            'pain_score' => 6,
            'consciousness_level' => 'alert',
            'pregnancy_status' => 'not_applicable',
            'gestational_age_weeks' => null,
            'danger_signs' => [],
            'allergies_confirmed' => true,
            'fall_risk' => 'low',
            'infection_risk' => 'suspected',
            'notes' => 'Patient requires prompt clinical review.',
        ];
    }

    private function service(string $name, string $code, string $type, User $admin): Service
    {
        $category = ServiceCategory::query()->first() ?: ServiceCategory::query()->create(['facility_id' => currentFacility()->id, 'name' => 'Clinical', 'code' => 'CLIN', 'category_type' => 'consultation', 'is_active' => true, 'created_by' => $admin->id]);
        $service = Service::query()->create(['facility_id' => currentFacility()->id, 'service_category_id' => $category->id, 'name' => $name, 'code' => $code, 'service_type' => $type, 'requires_payment' => true, 'is_active' => true, 'created_by' => $admin->id]);
        foreach (['cash', 'insurance'] as $payerType) {
            ServicePrice::query()->create(['facility_id' => currentFacility()->id, 'service_id' => $service->id, 'payer_type' => $payerType, 'amount' => 1000, 'currency' => 'TZS', 'is_active' => true, 'created_by' => $admin->id]);
        }

        return $service;
    }

    /** @return array{Visit, LaboratoryResult} */
    private function laboratoryResultFixture(User $admin, User $doctor, string $status, array $values, string $testName): array
    {
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);
        $service = $this->service($testName, 'LAB-'.fake()->unique()->numerify('######'), 'laboratory_test', $admin);
        $category = LaboratoryTestCategory::factory()->create(['facility_id' => currentFacility()->id, 'created_by' => $admin->id]);
        $specimen = SpecimenType::factory()->create(['facility_id' => currentFacility()->id, 'created_by' => $admin->id]);
        $test = LaboratoryTest::factory()->create([
            'facility_id' => currentFacility()->id,
            'service_id' => $service->id,
            'laboratory_test_category_id' => $category->id,
            'specimen_type_id' => $specimen->id,
            'name' => $testName,
            'code' => 'TST'.fake()->unique()->numerify('######'),
            'created_by' => $admin->id,
        ]);
        $order = app(ClinicalEncounterService::class)->addLabOrder(
            $encounter->refresh(),
            ['service_ids' => [$service->id]],
            $doctor,
        );
        $order->update(['status' => 'result_ready']);
        $item = $order->items()->firstOrFail();
        $item->update(['status' => 'sample_accepted', 'result_status' => $status, 'result_entered_at' => now()->subMinutes(10)]);
        $result = LaboratoryResult::query()->create([
            'facility_id' => currentFacility()->id,
            'laboratory_order_id' => $order->id,
            'laboratory_order_item_id' => $item->id,
            'laboratory_test_id' => $test->id,
            'result_version' => 1,
            'result_status' => $status,
            'comments' => 'Reviewed by laboratory',
            'entered_by' => $admin->id,
            'entered_at' => now()->subMinutes(10),
            'verified_by' => in_array($status, ['verified', 'released'], true) ? $admin->id : null,
            'verified_at' => in_array($status, ['verified', 'released'], true) ? now()->subMinutes(5) : null,
            'released_by' => $status === 'released' ? $admin->id : null,
            'released_at' => $status === 'released' ? now() : null,
            'created_by' => $admin->id,
        ]);

        foreach ($values as $index => $value) {
            LaboratoryResultValue::query()->create([
                'laboratory_result_id' => $result->id,
                'parameter_name_snapshot' => $value['parameter'],
                'parameter_code_snapshot' => 'P'.($index + 1),
                'result_type' => $value['type'],
                'numeric_value' => $value['numeric_value'] ?? null,
                'text_value' => $value['text_value'] ?? null,
                'selected_value' => $value['selected_value'] ?? null,
                'unit_snapshot' => $value['unit'] ?? null,
                'reference_range_snapshot' => $value['range'] ?? null,
                'abnormal_flag' => $value['flag'],
                'is_critical' => $value['critical'] ?? false,
                'sort_order' => $index,
                'created_by' => $admin->id,
            ]);
        }

        return [$visit->refresh(), $result->refresh()];
    }

    private function medicine(User $admin): Medicine
    {
        $unit = MedicineUnit::query()->create([
            'facility_id' => currentFacility()->id,
            'name' => 'Tablet',
            'symbol' => 'tab',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        return Medicine::query()->create([
            'facility_id' => currentFacility()->id,
            'purchase_unit_id' => $unit->id,
            'dispensing_unit_id' => $unit->id,
            'name' => 'Paracetamol',
            'code' => 'PCM-TEST',
            'strength' => '500mg',
            'pack_size' => 1,
            'purchase_to_dispensing_factor' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
    }
}
