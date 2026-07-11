<?php

namespace Tests\Feature\Patients;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Livewire\Patients\Index as PatientsIndex;
use App\Livewire\Reception\Index as ReceptionIndex;
use App\Livewire\Services\Categories\Index as ServiceCategoriesIndex;
use App\Models\Department;
use App\Models\Facility;
use App\Models\FacilitySetting;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\PatientDuplicateDetectionService;
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
use Livewire\Livewire;
use Tests\TestCase;

class Step5FoundationTest extends TestCase
{
    use RefreshDatabase;

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

        $this->assertSame('7000.00', $pricing->getCurrentPrice($service, \App\Enums\PayerType::Cash)->amount);
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

    public function test_duplicate_detection_finds_exact_phone_match(): void
    {
        $admin = $this->bootstrappedFacility();
        $patient = Patient::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_number' => 'PAT-2026-000001',
            'first_name' => 'John',
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
        $document = app(\App\Services\PatientDocumentService::class)->store($patient, UploadedFile::fake()->create('nida.pdf', 10, 'application/pdf'), ['document_type' => 'nida', 'document_name' => 'NIDA'], $admin);

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
        } catch (\Illuminate\Validation\ValidationException $exception) {
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
}
