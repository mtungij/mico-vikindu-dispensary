<?php

namespace Tests\Feature\Insurance;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Livewire\Insurance\Claims\Index as ClaimsIndex;
use App\Livewire\Insurance\Dashboard;
use App\Livewire\Insurance\Memberships\Index as MembershipsIndex;
use App\Livewire\Insurance\Reports\NhifClaimReport;
use App\Livewire\Insurance\Settings\Providers;
use App\Livewire\Insurance\Settings\Schemes;
use App\Models\Facility;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimBatch;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePayment;
use App\Models\InsuranceProvider;
use App\Models\InsuranceScheme;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\PatientInsuranceMembership;
use App\Models\Permission;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\InsuranceClaimBatchService;
use App\Services\InsuranceClaimPreparationService;
use App\Services\InsuranceClaimValidationService;
use App\Services\InsuranceCoverageService;
use App\Services\InsuranceEligibilityService;
use App\Services\InsurancePaymentService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\InsuranceProviderSeeder;
use Database\Seeders\InsuranceRejectionReasonSeeder;
use Database\Seeders\InsuranceSchemeSeeder;
use Database\Seeders\InsuranceSettingsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Step13InsuranceClaimsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_unauthorized_users_cannot_manage_insurance_settings(): void
    {
        $this->get(route('settings.insurance.providers'))->assertRedirect(route('login'));

        $this->bootstrappedFacility();
        $this->actingAs(User::factory()->create())->get(route('settings.insurance.providers'))->assertForbidden();
    }

    public function test_insurance_seeders_are_idempotent_and_settings_pages_render(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->seed([InsuranceProviderSeeder::class, InsuranceSchemeSeeder::class, InsuranceRejectionReasonSeeder::class, InsuranceSettingsSeeder::class]);
        $this->seed([InsuranceProviderSeeder::class, InsuranceSchemeSeeder::class, InsuranceRejectionReasonSeeder::class, InsuranceSettingsSeeder::class]);

        $this->assertSame(1, InsuranceProvider::query()->where('code', 'NHIF')->count());
        $this->assertGreaterThan(0, InsuranceScheme::query()->count());

        Livewire::actingAs($admin)->test(Providers::class)->assertOk();
        Livewire::actingAs($admin)->test(Schemes::class)->assertOk();
        Livewire::actingAs($admin)->test(Dashboard::class)->assertOk();
        Livewire::actingAs($admin)->test(MembershipsIndex::class)->assertOk();
        Livewire::actingAs($admin)->test(ClaimsIndex::class)->assertOk();
        Livewire::actingAs($admin)->test(NhifClaimReport::class)->assertOk();
    }

    public function test_membership_eligibility_coverage_claim_batch_payment_and_reports_work(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);
        $provider = InsuranceProvider::query()->where('code', 'NHIF')->firstOrFail();
        $scheme = InsuranceScheme::query()->where('insurance_provider_id', $provider->id)->firstOrFail();
        $service = Service::query()->forCurrentFacility()->firstOrFail();
        $patient = Patient::factory()->create(['facility_id' => currentFacility()->id, 'created_by' => $admin->id]);

        $membership = PatientInsuranceMembership::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'insurance_provider_id' => $provider->id,
            'insurance_scheme_id' => $scheme->id,
            'benefit_package_id' => $scheme->default_benefit_package_id,
            'membership_number' => 'NHIF-100',
            'membership_type' => 'principal',
            'valid_from' => today()->subMonth(),
            'valid_to' => today()->addMonth(),
            'verification_status' => 'unverified',
            'is_primary' => true,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        app(InsuranceEligibilityService::class)->recordVerification($membership, 'verified', 'manual');
        $this->assertTrue(app(InsuranceEligibilityService::class)->buildEligibilitySummary($membership->refresh())['eligible']);

        InsuranceCoverageRule::query()->create([
            'facility_id' => currentFacility()->id,
            'insurance_provider_id' => $provider->id,
            'insurance_scheme_id' => $scheme->id,
            'benefit_package_id' => $scheme->default_benefit_package_id,
            'rule_scope' => 'service',
            'service_id' => $service->id,
            'coverage_status' => 'partially_covered',
            'coverage_percentage' => 80,
            'patient_copayment_type' => 'fixed',
            'patient_copayment_value' => 1000,
            'is_active' => true,
        ]);

        $coverage = app(InsuranceCoverageService::class)->resolveServiceCoverage($membership, $service, 10000);
        $this->assertSame(7000.0, $coverage['payer_amount']);
        $this->assertSame(3000.0, $coverage['patient_amount']);

        $visit = Visit::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_number' => 'VIS-INS-001',
            'visit_type' => 'new_patient',
            'payer_type' => 'insurance',
            'visit_status' => 'registered',
            'registered_at' => now(),
            'created_by' => $admin->id,
        ]);

        $invoice = Invoice::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'invoice_number' => 'INV-INS-001',
            'payer_type' => 'insurance',
            'invoice_status' => 'pending',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'balance_amount' => 3000,
            'currency' => 'TZS',
            'issued_at' => now(),
            'created_by' => $admin->id,
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'service_id' => $service->id,
            'insurance_provider_id' => $provider->id,
            'insurance_scheme_id' => $scheme->id,
            'patient_insurance_membership_id' => $membership->id,
            'item_type' => 'consultation',
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => 10000,
            'total_amount' => 10000,
            'payer_amount' => 7000,
            'insurance_amount' => 7000,
            'patient_amount' => 3000,
            'coverage_percentage' => 80,
            'claimable_status' => 'claimable',
            'status' => 'pending',
            'metadata' => ['payer_service_code' => 'NHIF-CONS'],
            'created_by' => $admin->id,
        ]);

        $claim = app(InsuranceClaimPreparationService::class)->prepareVisitClaim($invoice, $membership);
        $claim->update(['primary_diagnosis_code' => 'A00']);
        $validation = app(InsuranceClaimValidationService::class)->validateClaim($claim->refresh());
        $this->assertTrue($validation['valid']);
        $this->assertSame('ready', $claim->refresh()->status);

        $batch = app(InsuranceClaimBatchService::class)->createBatch($provider, $scheme->id);
        app(InsuranceClaimBatchService::class)->addClaim($batch, $claim->refresh());
        $this->assertSame('batched', $claim->refresh()->status);
        $this->assertSame(1, $batch->refresh()->claims_count);

        $claim->update(['status' => 'approved', 'approved_amount' => 7000, 'outstanding_amount' => 7000]);
        $payment = InsurancePayment::query()->create([
            'facility_id' => currentFacility()->id,
            'insurance_provider_id' => $provider->id,
            'insurance_scheme_id' => $scheme->id,
            'payment_reference' => 'PAY-INS-001',
            'payment_date' => today(),
            'amount' => 7000,
            'payment_method' => 'bank_transfer',
            'status' => 'received',
            'received_by' => $admin->id,
        ]);
        app(InsurancePaymentService::class)->allocate($payment, $claim->refresh(), 7000);
        $this->assertSame('paid', $claim->refresh()->status);

        $this->actingAs($admin)->get(route('insurance.claims.reports.nhif'))->assertOk();
        $this->actingAs($admin)->get(route('reports.insurance.export', ['type' => 'nhif-claim-report']))->assertOk();
    }

    public function test_facility_scoping_blocks_cross_facility_records(): void
    {
        $admin = $this->bootstrappedFacility();
        $other = Facility::query()->create(['name'=>'Other Facility','code'=>'OTH','facility_type'=>FacilityType::Dispensary,'ownership_type'=>OwnershipType::Private,'phone_primary'=>'+255700000111','region'=>'Dar es Salaam','district'=>'Ilala','ward'=>'Upanga','physical_address'=>'Upanga','setup_completed_at'=>now(),'created_by'=>$admin->id,'updated_by'=>$admin->id]);
        InsuranceProvider::query()->create(['facility_id'=>$other->id,'name'=>'Other Insurer','code'=>'OTHER','provider_type'=>'private_insurance','claim_submission_method'=>'manual_report','is_active'=>true]);

        $this->assertFalse(InsuranceProvider::query()->forCurrentFacility()->where('code', 'OTHER')->exists());
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        Facility::query()->create(['name'=>'Vikindu Dispensary','code'=>'VDP','facility_type'=>FacilityType::Dispensary,'ownership_type'=>OwnershipType::Private,'phone_primary'=>'+255700000000','region'=>'Dar es Salaam','district'=>'Temeke','ward'=>'Vikindu','physical_address'=>'Vikindu','setup_completed_at'=>now(),'created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, ServiceCategorySeeder::class, ServiceSeeder::class, InsuranceProviderSeeder::class, InsuranceSchemeSeeder::class, InsuranceRejectionReasonSeeder::class, InsuranceSettingsSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) $admin->givePermissionTo($permission);

        return $admin;
    }
}
