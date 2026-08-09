<?php

namespace Tests\Feature\Patients;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Enums\PayerType;
use App\Livewire\Patients\Index as PatientsIndex;
use App\Livewire\Reception\Index as ReceptionIndex;
use App\Livewire\Services\Categories\Index as ServiceCategoriesIndex;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Facility;
use App\Models\FacilitySetting;
use App\Models\InsuranceProvider;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestCategory;
use App\Models\Patient;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Models\WorkflowSetting;
use App\Services\PatientDocumentService;
use App\Services\PatientDuplicateDetectionService;
use App\Services\PatientSearchService;
use App\Services\ReceptionChargeService;
use App\Services\ReceptionWorkflowService;
use App\Services\ServicePricingService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\InsuranceProviderSeeder;
use Database\Seeders\JobTitleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Database\Seeders\ServicePriceSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Step5FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_patient_search_is_ranked_and_facility_scoped(): void
    {
        $this->bootstrappedFacility();
        $exact = Patient::factory()->create(['facility_id' => currentFacility()->id, 'patient_number' => 'PAT-EXACT-1', 'first_name' => 'Asha', 'last_name' => 'Mtei', 'primary_phone' => '255712345678']);
        Patient::factory()->create(['facility_id' => currentFacility()->id, 'patient_number' => 'PAT-OTHER-1', 'first_name' => 'Exact', 'last_name' => 'One']);

        $results = app(PatientSearchService::class)->search('PAT-EXACT-1');

        $this->assertSame($exact->id, $results->first()->id);
        $this->assertTrue($results->every(fn ($patient) => $patient->facility_id === currentFacility()->id));
    }

    public function test_authorized_user_can_create_service_category(): void
    {
        $admin = $this->bootstrappedFacility();

        Livewire::actingAs($admin)
            ->test(ServiceCategoriesIndex::class)
            ->call('create')
            ->set('form.name', 'Imaging')
            ->set('form.code', 'img')
            ->set('form.category_type', 'imaging')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('service_categories', ['code' => 'IMG']);
    }

    public function test_service_price_versions_preserve_history_and_resolve_current_cash_price(): void
    {
        $admin = $this->bootstrappedFacility();
        $service = Service::query()->firstOrFail();
        $pricing = app(ServicePricingService::class);

        $pricing->createPriceVersion($service, ['payer_type' => 'cash', 'amount' => 5000, 'currency' => 'TZS'], $admin);
        $pricing->createPriceVersion($service, ['payer_type' => 'cash', 'amount' => 7000, 'currency' => 'TZS'], $admin);

        $this->assertSame('7000.00', $pricing->getCurrentPrice($service, PayerType::Cash)->amount);
        $this->assertSame(3, ServicePrice::query()->where('service_id', $service->id)->count());
    }

    public function test_patient_registration_creates_visit_invoice_and_queue_when_payment_not_required_first(): void
    {
        $admin = $this->bootstrappedFacility();
        $department = Department::query()->where('code', 'OPD')->firstOrFail();
        $department->update(['queue_enabled' => true, 'clinical_department' => true, 'requires_triage' => false]);
        $service = Service::query()->where('department_id', $department->id)->where('service_type', 'consultation')->firstOrFail();

        $result = app(ReceptionWorkflowService::class)->registerNewPatientAndVisit([
            'first_name' => 'Amina',
            'last_name' => 'Musa',
            'gender' => 'female',
            'age_years' => 28,
            'primary_phone' => '0712345678',
            'patient_status' => 'active',
        ], [
            'payer_type' => 'cash',
            'is_primary' => true,
        ], [
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => $department->id,
            'consultation_service_id' => $service->id,
            'priority' => 'normal',
            'source' => 'walk_in',
            'require_payment_before_service' => false,
        ], [], $admin);

        $this->assertStringStartsWith('PAT-', $result['patient']->patient_number);
        $this->assertStringStartsWith('VIS-', $result['visit']->visit_number);
        $this->assertNotNull($result['invoice']);
        $this->assertNotNull($result['queue']);
    }

    public function test_direct_laboratory_registration_requires_a_test_and_creates_no_opd_work(): void
    {
        $admin = $this->bootstrappedFacility();
        $laboratory = Department::query()->where('code', 'LAB')->firstOrFail();
        $laboratory->update(['queue_enabled' => true, 'requires_consultation' => false, 'requires_triage' => false]);
        [$service] = $this->directLaboratoryTest($admin, $laboratory, 7500);

        $data = [
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => $laboratory->id,
            'consultation_service_id' => null,
            'priority' => 'normal',
            'source' => 'walk_in',
            'registration_idempotency_key' => (string) Str::uuid(),
            'require_payment_before_service' => true,
        ];

        try {
            app(ReceptionWorkflowService::class)->registerNewPatientAndVisit([
                'first_name' => 'No',
                'last_name' => 'Test',
                'gender' => 'female',
                'age_years' => 20,
                'patient_status' => 'active',
            ], ['payer_type' => 'cash', 'is_primary' => true], $data, [], $admin);
            $this->fail('Direct laboratory registration continued without a test.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('selectedLaboratoryTestIds', $exception->errors());
        }

        $result = app(ReceptionWorkflowService::class)->registerNewPatientAndVisit([
            'first_name' => 'Direct',
            'last_name' => 'Laboratory',
            'gender' => 'female',
            'age_years' => 20,
            'patient_status' => 'active',
        ], ['payer_type' => 'cash', 'is_primary' => true], [
            ...$data,
            'registration_idempotency_key' => (string) Str::uuid(),
        ], [$service->id], $admin);

        $this->assertSame('reception_direct', $result['laboratoryOrder']->source);
        $this->assertNull($result['laboratoryOrder']->clinical_encounter_id);
        $this->assertSame('awaiting_payment', $result['visit']->visit_status->value);
        $this->assertDatabaseCount('clinical_encounters', 0);
        $this->assertDatabaseMissing('patient_queues', ['visit_id' => $result['visit']->id, 'department_id' => Department::query()->where('code', 'OPD')->value('id')]);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $result['invoice']->id, 'service_id' => $service->id, 'item_type' => 'laboratory_test']);
        $this->assertFalse($result['invoice']->items()->where('item_type', 'registration')->exists());
    }

    public function test_emergency_visit_does_not_automatically_receive_new_patient_registration_fee(): void
    {
        $admin = $this->bootstrappedFacility();
        [$department, $consultation] = $this->opdConsultation();
        $result = app(ReceptionWorkflowService::class)->registerNewPatientAndVisit([
            'first_name' => 'Emergency', 'last_name' => 'Patient', 'gender' => 'female', 'age_years' => 31, 'patient_status' => 'active',
        ], ['payer_type' => 'cash', 'is_primary' => true], [
            ...$this->visitData($department, $consultation), 'visit_type' => 'emergency', 'require_payment_before_service' => false,
        ], [], $admin);

        $this->assertFalse($result['invoice']->items()->where('item_type', 'registration')->exists());
        $this->assertFalse($result['invoice']->items()->where('item_type', 'consultation')->exists());
        $this->assertNotSame('awaiting_payment', $result['visit']->visit_status->value);
    }

    public function test_returning_and_emergency_visits_ignore_stale_general_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        [$department, $general] = $this->opdConsultation();
        $patient = $this->patient($admin);

        $returningPreview = app(ReceptionChargeService::class)->buildChargePreview(currentFacility(), false, $department->id, $general->id, ['payer_type' => 'cash'], [], 'returning_patient');
        $this->assertNull($returningPreview['consultation']);
        $this->assertEquals(0.0, $returningPreview['total']);

        $returning = app(ReceptionWorkflowService::class)->openReturningPatientVisit($patient, ['payer_type' => 'cash', 'is_primary' => true], [
            ...$this->visitData($department, $general), 'consultation_service_id' => $general->id,
        ], [], $admin);
        $this->assertNull($returning['visit']->consultation_service_id);
        $this->assertFalse($returning['invoice']->items()->where('item_type', 'consultation')->exists());
        $returning['visit']->update(['visit_status' => 'completed']);

        WorkflowSetting::query()->updateOrCreate(['facility_id' => currentFacility()->id, 'key' => 'allow_emergency_override'], ['value' => '1', 'type' => 'boolean', 'group' => 'workflow']);
        $emergencyPreview = app(ReceptionChargeService::class)->buildChargePreview(currentFacility(), true, $department->id, $general->id, ['payer_type' => 'cash', 'require_payment_before_service' => true], [], 'emergency');
        $this->assertNull($emergencyPreview['consultation']);
        $this->assertNotSame('Cashier/Billing', $emergencyPreview['next_step']);
        $emergency = app(ReceptionWorkflowService::class)->registerNewPatientAndVisit([
            'first_name' => 'Emergency', 'last_name' => 'No General Fee', 'gender' => 'female', 'age_years' => 28, 'patient_status' => 'active',
        ], ['payer_type' => 'cash', 'is_primary' => true], [
            ...$this->visitData($department, $general), 'visit_type' => 'emergency', 'consultation_service_id' => $general->id,
        ], [], $admin);
        $this->assertNull($emergency['visit']->consultation_service_id);
        $this->assertFalse($emergency['invoice']->items()->where('item_type', 'consultation')->exists());
        $this->assertNotSame('awaiting_payment', $emergency['visit']->visit_status->value);
    }

    public function test_visit_type_specific_consultation_requires_explicit_configuration_and_matches_preview(): void
    {
        $admin = $this->bootstrappedFacility();
        [$department, $general] = $this->opdConsultation();
        $specific = $general->replicate();
        $specific->name = 'Returning OPD Consultation';
        $specific->code = 'RETURN-OPD-CONSULT';
        $specific->save();
        app(ServicePricingService::class)->createPriceVersion($specific, ['payer_type' => 'cash', 'amount' => 2500, 'currency' => 'TZS'], $admin);
        FacilitySetting::query()->updateOrCreate(['facility_id' => currentFacility()->id, 'key' => 'charge_returning_patient_consultation'], ['value' => '1', 'type' => 'boolean', 'group' => 'reception_billing']);
        FacilitySetting::query()->updateOrCreate(['facility_id' => currentFacility()->id, 'key' => 'returning_patient_consultation_service_id'], ['value' => (string) $specific->id, 'type' => 'string', 'group' => 'reception_billing']);

        $preview = app(ReceptionChargeService::class)->buildChargePreview(currentFacility()->refresh(), false, $department->id, $general->id, ['payer_type' => 'cash'], [], 'returning_patient');
        $this->assertSame($specific->id, $preview['consultation']['service_id']);
        $patient = $this->patient($admin);
        $result = app(ReceptionWorkflowService::class)->openReturningPatientVisit($patient, ['payer_type' => 'cash', 'is_primary' => true], [
            ...$this->visitData($department, $general), 'consultation_service_id' => $general->id,
        ], [], $admin);

        $this->assertSame($specific->id, $result['visit']->consultation_service_id);
        $this->assertSame((float) $preview['total'], (float) $result['invoice']->refresh()->total_amount);
        $this->assertSame(1, $result['invoice']->items()->where('item_type', 'consultation')->count());
        $this->assertDatabaseMissing('invoice_items', ['invoice_id' => $result['invoice']->id, 'service_id' => $general->id]);

        $emergencySpecific = $general->replicate();
        $emergencySpecific->name = 'Emergency OPD Consultation';
        $emergencySpecific->code = 'EMERGENCY-OPD-CONSULT';
        $emergencySpecific->save();
        app(ServicePricingService::class)->createPriceVersion($emergencySpecific, ['payer_type' => 'cash', 'amount' => 3000, 'currency' => 'TZS'], $admin);
        FacilitySetting::query()->updateOrCreate(['facility_id' => currentFacility()->id, 'key' => 'charge_emergency_consultation'], ['value' => '1', 'type' => 'boolean', 'group' => 'reception_billing']);
        FacilitySetting::query()->updateOrCreate(['facility_id' => currentFacility()->id, 'key' => 'emergency_consultation_service_id'], ['value' => (string) $emergencySpecific->id, 'type' => 'string', 'group' => 'reception_billing']);
        $emergencyPreview = app(ReceptionChargeService::class)->buildChargePreview(currentFacility()->refresh(), true, $department->id, $general->id, ['payer_type' => 'cash'], [], 'emergency');
        $this->assertSame($emergencySpecific->id, $emergencyPreview['consultation']['service_id']);
        $emergency = app(ReceptionWorkflowService::class)->registerNewPatientAndVisit([
            'first_name' => 'Configured', 'last_name' => 'Emergency', 'gender' => 'male', 'age_years' => 35, 'patient_status' => 'active',
        ], ['payer_type' => 'cash', 'is_primary' => true], [
            ...$this->visitData($department, $general), 'visit_type' => 'emergency', 'consultation_service_id' => $general->id,
        ], [], $admin);
        $this->assertSame($emergencySpecific->id, $emergency['visit']->consultation_service_id);
        $this->assertSame((float) $emergencyPreview['total'], (float) $emergency['invoice']->refresh()->total_amount);
        $this->assertNotSame('awaiting_payment', $emergency['visit']->visit_status->value);
    }

    public function test_changing_new_visit_to_returning_or_emergency_clears_stale_consultation_preview(): void
    {
        $admin = $this->bootstrappedFacility();
        [$department, $general] = $this->opdConsultation();

        Livewire::actingAs($admin)->test(PatientsIndex::class)
            ->call('create')
            ->set('visit.destination_department_id', $department->id)
            ->set('visit.consultation_service_id', $general->id)
            ->assertSet('chargePreview.consultation.service_id', $general->id)
            ->set('visit.visit_type', 'returning_patient')
            ->assertSet('visit.consultation_service_id', null)
            ->assertSet('chargePreview.consultation', null)
            ->set('visit.visit_type', 'new_patient')
            ->assertSet('visit.consultation_service_id', $general->id)
            ->assertSet('chargePreview.consultation.service_id', $general->id)
            ->set('visit.visit_type', 'emergency')
            ->assertSet('visit.consultation_service_id', null)
            ->assertSet('chargePreview.consultation', null);
    }

    public function test_returning_visit_without_selected_patient_is_blocked_and_sent_to_search(): void
    {
        $admin = $this->bootstrappedFacility();
        [$department] = $this->opdConsultation();

        Livewire::actingAs($admin)->test(PatientsIndex::class)
            ->call('create')
            ->set('visit.visit_type', 'returning_patient')
            ->set('visit.destination_department_id', $department->id)
            ->set('step', 6)
            ->assertSee('Chagua mgonjwa wa zamani kabla ya kuhifadhi Returning Visit.')
            ->call('save')
            ->assertSet('step', 1)
            ->assertHasErrors(['selectedPatientId', 'save'])
            ->assertSee('Returning visit requires selecting an existing patient first.');

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_selected_existing_patient_persists_and_saves_only_a_returning_visit(): void
    {
        $admin = $this->bootstrappedFacility();
        $patient = $this->patient($admin);
        [$department] = $this->opdConsultation();
        $patientCount = Patient::query()->count();

        Livewire::actingAs($admin)->test(PatientsIndex::class)
            ->call('create')
            ->call('selectExistingPatient', $patient->id)
            ->assertSet('selectedPatientId', $patient->id)
            ->assertSet('visit.visit_type', 'returning_patient')
            ->set('visit.destination_department_id', $department->id)
            ->set('visit.require_payment_before_service', false)
            ->set('step', 6)
            ->assertSee($patient->fullName())
            ->assertSee($patient->patient_number)
            ->assertSee('Patient status: Existing')
            ->assertSee('Inahifadhi...')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('patients.show', $patient));

        $this->assertSame($patientCount, Patient::query()->count());
        $visit = Visit::query()->where('patient_id', $patient->id)->sole();
        $this->assertSame('returning_patient', $visit->visit_type->value);
        $this->assertSame(1, $visit->queues()->count());
        $this->assertDatabaseHas('activity_logs', ['event' => 'visit_created', 'subject_type' => Visit::class, 'subject_id' => $visit->id]);
        $this->assertFalse($visit->invoice->items()->where('item_type', 'registration')->exists());
    }

    public function test_active_visit_blocks_returning_save_without_override_and_is_visible(): void
    {
        $admin = $this->bootstrappedFacility();
        [$department, $consultation] = $this->opdConsultation();
        $existing = $this->registerPatient($admin, $department, $consultation, 'cash', 'Active');
        $user = User::factory()->create();
        StaffProfile::factory()->create(['user_id' => $user->id, 'facility_id' => currentFacility()->id]);
        $user->givePermissionTo(['patients.view', 'patients.create', 'reception.open-visit']);

        Livewire::actingAs($user)->test(PatientsIndex::class)
            ->call('create')
            ->call('selectExistingPatient', $existing['patient']->id)
            ->set('visit.destination_department_id', $department->id)
            ->set('step', 6)
            ->assertSee('Mgonjwa huyu tayari ana active visit '.$existing['visit']->visit_number.'.')
            ->assertSee('Open Active Visit')
            ->assertSee('Continue Existing Visit')
            ->call('save')
            ->assertHasErrors(['activeVisit']);

        $this->assertSame(1, Visit::query()->where('patient_id', $existing['patient']->id)->count());
    }

    public function test_active_visit_override_permission_allows_a_new_returning_visit(): void
    {
        $admin = $this->bootstrappedFacility();
        [$department, $consultation] = $this->opdConsultation();
        $existing = $this->registerPatient($admin, $department, $consultation, 'cash', 'Override');

        Livewire::actingAs($admin)->test(PatientsIndex::class)
            ->call('create')
            ->call('selectExistingPatient', $existing['patient']->id)
            ->set('visit.destination_department_id', $department->id)
            ->set('visit.require_payment_before_service', false)
            ->set('step', 6)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Visit::query()->where('patient_id', $existing['patient']->id)->count());
        $this->assertSame(1, Visit::query()->where('patient_id', $existing['patient']->id)->where('visit_type', 'returning_patient')->count());
    }

    public function test_returning_registration_idempotency_prevents_duplicate_visit_invoice_and_queue(): void
    {
        $admin = $this->bootstrappedFacility();
        $patient = $this->patient($admin);
        [$department, $consultation] = $this->opdConsultation();
        $data = [
            ...$this->visitData($department, $consultation),
            'visit_type' => 'returning_patient',
            'registration_idempotency_key' => (string) Str::uuid(),
            'require_payment_before_service' => false,
        ];

        $first = app(ReceptionWorkflowService::class)->openReturningPatientVisit($patient, ['payer_type' => 'cash', 'is_primary' => true], $data, [], $admin);
        $second = app(ReceptionWorkflowService::class)->openReturningPatientVisit($patient->refresh(), ['payer_type' => 'cash', 'is_primary' => true], $data, [], $admin);

        $this->assertSame($first['visit']->id, $second['visit']->id);
        $this->assertSame(1, Visit::query()->where('patient_id', $patient->id)->count());
        $this->assertSame(1, $first['visit']->invoice()->count());
        $this->assertSame(1, $first['visit']->queues()->count());
        $this->assertSame(1, ActivityLog::query()->where('event', 'visit_created')->where('subject_type', Visit::class)->where('subject_id', $first['visit']->id)->count());
    }

    public function test_returning_save_authorization_failure_is_shown_on_final_step(): void
    {
        $this->bootstrappedFacility();
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $patient = $this->patient($admin);
        [$department] = $this->opdConsultation();
        $user = User::factory()->create();
        StaffProfile::factory()->create(['user_id' => $user->id, 'facility_id' => currentFacility()->id]);
        $user->givePermissionTo(['patients.view', 'patients.create']);

        Livewire::actingAs($user)->test(PatientsIndex::class)
            ->call('create')
            ->call('selectExistingPatient', $patient->id)
            ->set('visit.destination_department_id', $department->id)
            ->set('step', 6)
            ->call('save')
            ->assertHasErrors(['authorization'])
            ->assertSee('Huna ruhusa ya kusajili visit hii.');

        $this->assertDatabaseCount('visits', 0);
    }

    public function test_direct_laboratory_registration_is_idempotent(): void
    {
        $admin = $this->bootstrappedFacility();
        $laboratory = Department::query()->where('code', 'LAB')->firstOrFail();
        $laboratory->update(['queue_enabled' => true, 'requires_consultation' => false, 'requires_triage' => false]);
        [$service] = $this->directLaboratoryTest($admin, $laboratory, 0);
        $key = (string) Str::uuid();
        $patient = ['first_name' => 'Double', 'last_name' => 'Click', 'gender' => 'male', 'age_years' => 40, 'patient_status' => 'active'];
        $payer = ['payer_type' => 'cash', 'is_primary' => true];
        $visit = ['visit_type' => 'new_patient', 'payer_type' => 'cash', 'destination_department_id' => $laboratory->id, 'consultation_service_id' => null, 'priority' => 'normal', 'source' => 'walk_in', 'registration_idempotency_key' => $key, 'require_payment_before_service' => true];

        $first = app(ReceptionWorkflowService::class)->registerNewPatientAndVisit($patient, $payer, $visit, [$service->id], $admin);
        $second = app(ReceptionWorkflowService::class)->registerNewPatientAndVisit($patient, $payer, $visit, [$service->id], $admin);

        $this->assertSame($first['visit']->id, $second['visit']->id);
        $this->assertSame(1, LaboratoryOrder::query()->where('visit_id', $first['visit']->id)->count());
        $this->assertSame(1, $first['invoice']->items()->where('service_id', $service->id)->count());
        $this->assertSame(1, $first['visit']->queues()->where('department_id', $laboratory->id)->count());
    }

    public function test_duplicate_detection_finds_exact_phone_match(): void
    {
        $admin = $this->bootstrappedFacility();
        $patient = Patient::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_number' => 'PAT-2026-000001',
            'first_name' => 'James',
            'last_name' => 'Doe',
            'gender' => 'male',
            'primary_phone' => '+255712345678',
            'patient_status' => 'active',
            'created_by' => $admin->id,
        ]);

        $result = app(PatientDuplicateDetectionService::class)->detect(['primary_phone' => '+255712345678']);

        $this->assertSame('exact', $result['status']);
        $this->assertTrue($result['exact']->contains($patient));
    }

    public function test_patient_document_upload_and_download_authorization(): void
    {
        Storage::fake('local');
        $admin = $this->bootstrappedFacility();
        $patient = $this->patient($admin);
        $document = app(PatientDocumentService::class)->store($patient, UploadedFile::fake()->create('nida.pdf', 10, 'application/pdf'), ['document_type' => 'nida', 'document_name' => 'NIDA'], $admin);

        Storage::disk('local')->assertExists($document->file_path);
        $this->actingAs($admin)->get(route('patients.documents.download', [$patient, $document]))->assertOk();

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('patients.documents.download', [$patient, $document]))->assertForbidden();
    }

    public function test_patient_list_and_reception_dashboard_render(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->patient($admin);

        Livewire::actingAs($admin)->test(PatientsIndex::class)->assertOk();
        Livewire::actingAs($admin)->test(ReceptionIndex::class)->assertOk();
    }

    public function test_new_cash_patient_gets_registration_and_consultation_charges_automatically(): void
    {
        $admin = $this->bootstrappedFacility();
        [$department, $consultation] = $this->opdConsultation();
        $newReg = Service::query()->where('code', 'NEW-REG')->firstOrFail();
        $pricing = app(ServicePricingService::class);
        $pricing->createPriceVersion($newReg, ['payer_type' => 'cash', 'amount' => 2000, 'currency' => 'TZS'], $admin);
        $pricing->createPriceVersion($consultation, ['payer_type' => 'cash', 'amount' => 10000, 'currency' => 'TZS'], $admin);

        $result = $this->registerPatient($admin, $department, $consultation, 'cash');

        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $result['invoice']->id, 'service_id' => $newReg->id, 'item_type' => 'registration', 'total_amount' => 2000]);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $result['invoice']->id, 'service_id' => $consultation->id, 'item_type' => 'consultation', 'total_amount' => 10000]);
        $this->assertSame('awaiting_payment', $result['visit']->visit_status->value);
        $this->assertNull($result['queue']);
    }

    public function test_returning_patient_registration_charge_respects_facility_setting(): void
    {
        $admin = $this->bootstrappedFacility();
        $patient = $this->patient($admin);
        [$department, $consultation] = $this->opdConsultation();
        $returnReg = Service::query()->where('code', 'RETURN-REG')->firstOrFail();
        app(ServicePricingService::class)->createPriceVersion($returnReg, ['payer_type' => 'cash', 'amount' => 1000, 'currency' => 'TZS'], $admin);

        $first = app(ReceptionWorkflowService::class)->openReturningPatientVisit($patient, ['payer_type' => 'cash', 'is_primary' => true], $this->visitData($department, $consultation), [], $admin);
        $this->assertDatabaseMissing('invoice_items', ['invoice_id' => $first['invoice']->id, 'service_id' => $returnReg->id]);
        $first['visit']->update(['visit_status' => 'completed']);

        FacilitySetting::query()->updateOrCreate(['facility_id' => currentFacility()->id, 'key' => 'charge_returning_patient_registration'], ['value' => '1', 'type' => 'boolean', 'group' => 'reception_billing']);
        $second = app(ReceptionWorkflowService::class)->openReturningPatientVisit($patient->refresh(), ['payer_type' => 'cash', 'is_primary' => true], $this->visitData($department, $consultation), [], $admin);

        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $second['invoice']->id, 'service_id' => $returnReg->id, 'total_amount' => 1000]);
    }

    public function test_missing_price_blocks_registration_but_zero_price_is_free(): void
    {
        $admin = $this->bootstrappedFacility();
        [$department, $consultation] = $this->opdConsultation();
        $newReg = Service::query()->where('code', 'NEW-REG')->firstOrFail();
        ServicePrice::query()->whereIn('service_id', [$newReg->id, $consultation->id])->delete();

        try {
            $this->registerPatient($admin, $department, $consultation, 'cash');
            $this->fail('Missing service price should block registration.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('bado haijawekewa bei', collect($exception->errors())->flatten()->first());
        }

        app(ServicePricingService::class)->createPriceVersion($newReg, ['payer_type' => 'cash', 'amount' => 0, 'currency' => 'TZS'], $admin);
        app(ServicePricingService::class)->createPriceVersion($consultation, ['payer_type' => 'cash', 'amount' => 0, 'currency' => 'TZS'], $admin);
        $result = $this->registerPatient($admin, $department, $consultation, 'cash', 'Free');
        $this->assertSame('0.00', $result['invoice']->refresh()->total_amount);
    }

    public function test_insurance_provider_specific_price_is_covered_by_insurance(): void
    {
        $admin = $this->bootstrappedFacility();
        [$department, $consultation] = $this->opdConsultation();
        $provider = InsuranceProvider::query()->firstOrFail();
        $newReg = Service::query()->where('code', 'NEW-REG')->firstOrFail();
        app(ServicePricingService::class)->createPriceVersion($newReg, ['payer_type' => 'insurance', 'insurance_provider_id' => $provider->id, 'amount' => 0, 'currency' => 'TZS'], $admin);
        app(ServicePricingService::class)->createPriceVersion($consultation, ['payer_type' => 'insurance', 'insurance_provider_id' => $provider->id, 'amount' => 15000, 'currency' => 'TZS'], $admin);

        $result = $this->registerPatient($admin, $department, $consultation, 'insurance', 'Insured', ['insurance_provider_id' => $provider->id, 'membership_number' => 'NHIF-001']);

        $item = $result['invoice']->items()->where('service_id', $consultation->id)->firstOrFail();
        $this->assertEquals(15000.00, (float) $item->insurance_amount);
        $this->assertEquals(0.00, (float) $item->patient_amount);
        $this->assertNotSame('awaiting_payment', $result['visit']->visit_status->value);
    }

    public function test_duplicate_submission_does_not_duplicate_auto_invoice_items(): void
    {
        $admin = $this->bootstrappedFacility();
        [$department, $consultation] = $this->opdConsultation();
        $result = $this->registerPatient($admin, $department, $consultation, 'cash');
        $service = app(ReceptionChargeService::class);
        $newReg = Service::query()->where('code', 'NEW-REG')->firstOrFail();

        $service->createInitialInvoiceItems($result['invoice'], $newReg, $consultation, true, $department, $admin);
        $service->createInitialInvoiceItems($result['invoice'], $newReg, $consultation, true, $department, $admin);

        $this->assertSame(1, $result['invoice']->items()->where('service_id', $newReg->id)->count());
        $this->assertSame(1, $result['invoice']->items()->where('service_id', $consultation->id)->count());
    }

    public function test_patient_card_replacement_is_separate_action_and_creates_invoice_item(): void
    {
        $admin = $this->bootstrappedFacility();
        $patient = $this->patient($admin);
        $service = Service::query()->where('code', 'CARD-REPLACE')->firstOrFail();
        app(ServicePricingService::class)->createPriceVersion($service, ['payer_type' => 'cash', 'amount' => 500, 'currency' => 'TZS'], $admin);

        $invoice = app(ReceptionChargeService::class)->requestPatientCardReplacement($patient, ['reason' => 'lost', 'quantity' => 1], $admin);

        $this->assertNull($invoice->visit_id);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id, 'service_id' => $service->id, 'item_type' => 'administrative_service', 'total_amount' => 500]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'patient_card_replacement_requested', 'subject_type' => Patient::class, 'subject_id' => $patient->id]);
    }

    public function test_patient_and_reception_csv_exports_work(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->patient($admin);

        $this->actingAs($admin)->get(route('reports.patients.export'))->assertOk()->assertHeader('content-disposition');
        $this->actingAs($admin)->get(route('reports.reception.export'))->assertOk()->assertHeader('content-disposition');
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
        $this->seed([PermissionSeeder::class, RoleSeeder::class, DepartmentSeeder::class, JobTitleSeeder::class, InsuranceProviderSeeder::class, ServiceCategorySeeder::class, ServiceSeeder::class, ServicePriceSeeder::class, RolePermissionSeeder::class]);

        return $admin;
    }

    private function patient(User $admin): Patient
    {
        return Patient::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_number' => 'PAT-2026-000009',
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'male',
            'age_years' => 30,
            'patient_status' => 'active',
            'created_by' => $admin->id,
            'registered_at' => now(),
        ]);
    }

    private function opdConsultation(): array
    {
        $department = Department::query()->where('code', 'OPD')->firstOrFail();
        $department->update(['queue_enabled' => true, 'requires_triage' => false]);
        $consultation = Service::query()->where('department_id', $department->id)->where('service_type', 'consultation')->firstOrFail();

        return [$department->refresh(), $consultation];
    }

    private function visitData(Department $department, Service $consultation): array
    {
        return [
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => $department->id,
            'consultation_service_id' => $consultation->id,
            'priority' => 'normal',
            'source' => 'walk_in',
            'require_payment_before_service' => true,
        ];
    }

    private function registerPatient(User $admin, Department $department, Service $consultation, string $payerType, string $firstName = 'Asha', array $payerOverrides = []): array
    {
        return app(ReceptionWorkflowService::class)->registerNewPatientAndVisit([
            'first_name' => $firstName,
            'last_name' => 'Autocharge',
            'gender' => 'female',
            'age_years' => 22,
            'patient_status' => 'active',
        ], [
            'payer_type' => $payerType,
            'is_primary' => true,
            ...$payerOverrides,
        ], [
            ...$this->visitData($department, $consultation),
            'payer_type' => $payerType,
        ], [], $admin);
    }

    private function directLaboratoryTest(User $admin, Department $department, float $amount): array
    {
        $category = ServiceCategory::query()->where('category_type', 'laboratory')->firstOrFail();
        $service = Service::query()->create([
            'facility_id' => currentFacility()->id,
            'service_category_id' => $category->id,
            'department_id' => $department->id,
            'name' => 'Direct CBC '.$amount,
            'code' => 'DLAB'.(int) $amount,
            'service_type' => 'laboratory_test',
            'requires_payment' => true,
            'allows_walk_in' => true,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $testCategory = LaboratoryTestCategory::query()->create([
            'facility_id' => currentFacility()->id,
            'name' => 'Direct Tests',
            'code' => 'DIRECT',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $test = LaboratoryTest::query()->create([
            'facility_id' => currentFacility()->id,
            'service_id' => $service->id,
            'laboratory_test_category_id' => $testCategory->id,
            'name' => $service->name,
            'code' => $service->code,
            'result_type' => 'numeric',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        app(ServicePricingService::class)->createPriceVersion($service, ['payer_type' => 'cash', 'amount' => $amount, 'currency' => 'TZS'], $admin);

        return [$service, $test];
    }
}
