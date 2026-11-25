<?php

namespace Tests\Feature\Rch;

use App\Enums\PayerType;
use App\Enums\RchService;
use App\Models\ActivityLog;
use App\Models\Facility;
use App\Models\InsuranceProvider;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestCategory;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SpecimenType;
use App\Models\User;
use App\Models\Visit;
use App\Services\InvoiceService;
use App\Services\PaymentConfirmationService;
use App\Services\ReceptionChargeService;
use App\Services\ReceptionWorkflowService;
use App\Services\ServicePricingService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RchWorkflowServiceSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RchWorkflowServiceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_ults_lab_service_coexists_with_rch_ultrasound_on_case_insensitive_catalogue(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Match the case-insensitive service name constraint used by production.
            DB::statement('CREATE UNIQUE INDEX services_facility_name_nocase ON services (facility_id, name COLLATE NOCASE)');
        }

        $admin = User::factory()->superAdmin()->create();
        $facility = Facility::factory()->create(['created_by' => $admin->id]);
        $this->actingAs($admin);
        $this->seed([DepartmentSeeder::class, ServiceCategorySeeder::class]);

        $laboratory = $facility->departments()->where('code', 'LAB')->sole();
        $rch = $facility->departments()->where('code', 'RCH')->sole();
        $legacy = Service::factory()->create([
            'id' => 105,
            'facility_id' => $facility->id,
            'department_id' => $laboratory->id,
            'service_category_id' => ServiceCategory::query()->where('facility_id', $facility->id)->where('code', 'LAB')->sole()->id,
            'code' => 'ULTS',
            'name' => 'ultrasound',
            'service_type' => 'laboratory_test',
        ]);
        $legacyPrice = $legacy->prices()->create([
            'facility_id' => $facility->id,
            'payer_type' => 'cash',
            'amount' => 23000,
            'currency' => 'TZS',
            'is_active' => true,
        ]);
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $facility->id,
            'service_id' => $legacy->id,
            'laboratory_test_category_id' => LaboratoryTestCategory::factory()->create(['facility_id' => $facility->id])->id,
            'specimen_type_id' => SpecimenType::factory()->create(['facility_id' => $facility->id])->id,
            'code' => $legacy->code,
            'name' => $legacy->name,
        ]);
        $history = app(ReceptionWorkflowService::class)->registerNewPatientAndVisit(
            ['first_name' => 'Test', 'last_name' => 'Patient', 'gender' => 'female', 'age_years' => 29, 'patient_status' => 'active'],
            ['payer_type' => 'cash', 'is_primary' => true],
            ['visit_type' => 'new_patient', 'destination_department_id' => $laboratory->id],
            [$legacy->id],
            $admin,
        );
        $order = $history['laboratoryOrder'];
        $invoice = $history['invoice']->refresh();
        $orderItem = $order->items()->sole();
        $invoiceItem = $invoice->items()->sole();
        $this->assertSame($legacy->id, $orderItem->service_id);
        $this->assertSame($legacy->id, $invoiceItem->service_id);
        $legacyRecords = [$legacy, $legacyPrice, $test, $order, $orderItem, $invoice, $invoiceItem];
        foreach ($legacyRecords as $record) {
            $record->refresh();
        }

        $this->seed(RchWorkflowServiceSeeder::class);

        $catalogue = app(ReceptionChargeService::class)->rchServices($rch)->pluck('code')->all();
        $this->assertEqualsCanonicalizing(['CGM', 'FPCON', 'RCH-ANC', 'RCH-PNC', 'RCH-US'], $catalogue);
        $ultrasound = Service::query()->where('facility_id', $facility->id)->where('code', 'RCH-US')->sole();
        $this->assertSame('RCH Ultrasound', $ultrasound->name);
        $this->assertSame('procedure', $ultrasound->service_type->value);
        $this->assertSame($rch->id, $ultrasound->department_id);
        $price = $ultrasound->prices()->sole();
        $this->assertSame('15000.00', $price->amount);

        $price->update(['amount' => 18250]);
        $this->seed(RchWorkflowServiceSeeder::class);

        $this->assertSame($ultrasound->id, Service::query()->where('facility_id', $facility->id)->where('code', 'RCH-US')->sole()->id);
        $this->assertTrue($price->is($ultrasound->prices()->sole()));
        $this->assertSame('18250.00', $price->fresh()->amount);

        foreach ($legacyRecords as $record) {
            $this->assertSame($record->getRawOriginal(), $record->fresh()->getRawOriginal());
        }
        $this->assertTrue($legacyPrice->is($legacy->prices()->sole()));
    }

    public function test_targeted_update_repairs_existing_records_and_preserves_history_and_other_facilities(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $facility = Facility::factory()->create(['created_by' => $admin->id]);
        $otherFacility = Facility::factory()->create();
        $this->actingAs($admin);
        $this->seed([DepartmentSeeder::class, ServiceCategorySeeder::class, RchWorkflowServiceSeeder::class, PaymentMethodSeeder::class]);
        $department = $facility->departments()->where('code', 'RCH')->sole();
        $services = Service::query()->where('facility_id', $facility->id)->get()->keyBy('code');
        $growth = $services[RchService::ChildMonitoring->value];
        $familyPlanning = $services[RchService::FamilyPlanning->value];
        $growth->update(['department_id' => $facility->departments()->where('code', 'OPD')->sole()->id, 'requires_payment' => false]);
        $growth->prices()->sole()->update(['amount' => 700]);
        $familyPlanning->prices()->sole()->update(['amount' => 5000]);
        $familyPlanning->update(['is_active' => false, 'service_type' => 'consultation', 'requires_payment' => true]);

        $historyVisit = Visit::factory()->create([
            'facility_id' => $facility->id, 'patient_id' => Patient::factory()->create(['facility_id' => $facility->id])->id,
            'destination_department_id' => $department->id, 'current_department_id' => $department->id,
        ]);
        $historicalInvoice = app(InvoiceService::class)->createVisitInvoice($historyVisit, [$familyPlanning], $admin)->refresh();
        $historicalPayment = app(PaymentConfirmationService::class)->confirmPayment(
            $historicalInvoice, PaymentMethod::query()->where('code', 'CASH')->firstOrFail(), 5000, $admin,
        );
        $historicalItem = $historicalInvoice->refresh()->items()->sole();
        $this->assertSame('5000.00', $historicalItem->unit_price);

        $other = Service::factory()->create(['facility_id' => $otherFacility->id, 'code' => 'FPCON', 'is_active' => false]);
        $futurePrice = $growth->prices()->create([
            'facility_id' => $facility->id, 'payer_type' => 'cash', 'currency' => 'TZS',
            'amount' => 1500, 'effective_from' => today()->addWeek(), 'is_active' => true,
        ]);
        $services['RCH-US']->prices()->sole()->update(['amount' => 18250]);
        $arguments = ['--facility' => $facility->id];
        $this->artisan('rch:configure-catalogue', $arguments)->assertSuccessful();
        $this->artisan('rch:configure-catalogue', [...$arguments, '--apply' => true])->assertFailed();
        $this->assertSame(3, app(ReceptionChargeService::class)->rchServices($department)->count());
        $this->assertSame('5000.00', $familyPlanning->prices()->sole()->amount);

        $apply = [...$arguments, '--apply' => true, '--actor' => $admin->id];
        $this->artisan('rch:configure-catalogue', $apply)->assertSuccessful();
        $this->artisan('rch:configure-catalogue', $apply)->assertSuccessful();
        $this->assertEqualsCanonicalizing(array_column(RchService::cases(), 'value'), app(ReceptionChargeService::class)->rchServices($department)->pluck('code')->all());
        $this->assertSame($growth->id, app(ReceptionChargeService::class)->rchServices($department)->where('code', 'CGM')->sole()->id);
        $this->assertSame($familyPlanning->id, app(ReceptionChargeService::class)->rchServices($department)->where('code', 'FPCON')->sole()->id);
        $this->assertSame('500.00', app(ServicePricingService::class)->getCurrentPrice($growth, PayerType::Cash)->amount);
        $this->assertSame('0.00', app(ServicePricingService::class)->getCurrentPrice($familyPlanning, PayerType::Cash)->amount);
        $this->assertSame('18250.00', $services['RCH-US']->prices()->sole()->amount);
        $this->assertFalse($futurePrice->fresh()->is_active);
        $this->assertFalse($other->fresh()->is_active);
        $this->assertSame(2, ActivityLog::query()->where('event', 'rch_catalogue_configured')->count());
        $this->assertSame(2, $familyPlanning->prices()->count());
        $this->assertSame($historicalInvoice->getRawOriginal(), $historicalInvoice->fresh()->getRawOriginal());
        $this->assertSame($historicalItem->getRawOriginal(), $historicalItem->fresh()->getRawOriginal());
        $this->assertSame($historicalPayment->getRawOriginal(), $historicalPayment->fresh()->getRawOriginal());
    }

    public function test_update_blocks_conflicting_contract_tariffs_and_ambiguous_service_aliases(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $facility = Facility::factory()->create(['created_by' => $admin->id]);
        $this->actingAs($admin);
        $this->seed([DepartmentSeeder::class, ServiceCategorySeeder::class, RchWorkflowServiceSeeder::class]);
        $services = Service::query()->where('facility_id', $facility->id)->get()->keyBy('code');
        $services['CGM']->update(['code' => 'GROWTH-CUSTOM']);
        $payer = InsuranceProvider::query()->create([
            'facility_id' => $facility->id, 'name' => 'Existing insurer', 'code' => 'LEGACY', 'provider_type' => 'private_insurance', 'is_active' => true,
        ]);
        $price = $services['FPCON']->prices()->create([
            'facility_id' => $facility->id, 'payer_type' => 'insurance', 'insurance_provider_id' => $payer->id,
            'amount' => 3000, 'currency' => 'TZS', 'effective_from' => today()->addDay(), 'is_active' => true,
        ]);
        $arguments = ['--facility' => $facility->id];
        $this->artisan('rch:configure-catalogue', $arguments)
            ->expectsOutputToContain('GROWTH-CUSTOM')->expectsOutputToContain("Family Planning tariff #{$price->id}")->assertFailed();
        $this->artisan('rch:configure-catalogue', [...$arguments, '--apply' => true, '--actor' => $admin->id])->assertFailed();
        $this->assertDatabaseMissing('services', ['facility_id' => $facility->id, 'code' => 'CGM']);
        $this->assertTrue($price->fresh()->is_active);
        $this->assertSame('3000.00', $price->fresh()->amount);
        $this->assertSame(0, ActivityLog::query()->where('event', 'rch_catalogue_configured')->count());
    }
}
