<?php

namespace Tests\Feature\Rch;

use App\Models\Facility;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestCategory;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SpecimenType;
use App\Models\User;
use App\Services\ReceptionChargeService;
use App\Services\ReceptionWorkflowService;
use Database\Seeders\DepartmentSeeder;
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
        $this->assertEqualsCanonicalizing(['CGM', 'RCH-ANC', 'RCH-PNC', 'RCH-US'], $catalogue);
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
}
