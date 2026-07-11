<?php

namespace Tests\Feature\Pharmacy;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Enums\ServiceType;
use App\Livewire\Pharmacy\Queue as PharmacyQueue;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Visit;
use App\Services\PharmacyBatchAllocationService;
use App\Services\PharmacyDispensingService;
use App\Services\StockReceivingService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\DosageFormSeeder;
use Database\Seeders\GenericMedicineSeeder;
use Database\Seeders\MedicineCategorySeeder;
use Database\Seeders\MedicineRouteSeeder;
use Database\Seeders\MedicineUnitSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Step8PharmacyInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_pharmacy_queue(): void
    {
        $this->get(route('pharmacy.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_access_pharmacy_pages(): void
    {
        $admin = $this->bootstrappedFacility();

        Livewire::actingAs($admin)->test(PharmacyQueue::class)->assertOk();
        $this->actingAs($admin)->get(route('pharmacy.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('settings.pharmacy.categories'))->assertOk();
        $this->actingAs($admin)->get(route('pharmacy.medicines.index'))->assertOk();
        $this->actingAs($admin)->get(route('reports.pharmacy', 'stock-movement'))->assertOk();
        $this->actingAs($admin)->get(route('reports.pharmacy.export', 'stock-movement'))->assertOk();
    }

    public function test_receiving_stock_creates_batch_and_immutable_movement(): void
    {
        $admin = $this->bootstrappedFacility();
        [$medicine, $supplier, $location] = $this->catalog();

        $receipt = app(StockReceivingService::class)->receive([
            'supplier_id' => $supplier->id,
            'stock_location_id' => $location->id,
            'supplier_invoice_number' => 'INV-001',
        ], [[
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-A',
            'expiry_date' => today()->addYear()->toDateString(),
            'quantity_received' => 50,
            'unit_cost' => 25,
        ]], $admin);

        $this->assertStringStartsWith('RCV-', $receipt->receipt_number);
        $this->assertDatabaseHas('medicine_batches', ['medicine_id' => $medicine->id, 'batch_number' => 'BATCH-A', 'available_quantity' => 50]);
        $this->assertDatabaseHas('stock_movements', ['medicine_id' => $medicine->id, 'quantity' => 50, 'movement_type' => 'purchase_receipt']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'stock_received', 'subject_id' => $receipt->id]);
    }

    public function test_fefo_allocation_uses_earliest_non_expired_batch(): void
    {
        $admin = $this->bootstrappedFacility();
        [$medicine, $supplier, $location] = $this->catalog();
        $this->receiveBatch($admin, $medicine, $supplier, $location, 'LATE', today()->addMonths(6)->toDateString(), 10);
        $this->receiveBatch($admin, $medicine, $supplier, $location, 'EARLY', today()->addMonth()->toDateString(), 10);

        $allocations = app(PharmacyBatchAllocationService::class)->allocateFefo($medicine, $location, '5');

        $this->assertSame('EARLY', $allocations[0]['batch']->batch_number);
        $this->assertSame(5.0, $allocations[0]['quantity']);
    }

    public function test_dispensing_deducts_stock_updates_prescription_and_records_audit(): void
    {
        $admin = $this->bootstrappedFacility();
        [$medicine, $supplier, $location] = $this->catalog();
        $this->receiveBatch($admin, $medicine, $supplier, $location, 'DSP', today()->addYear()->toDateString(), 20);
        $prescription = $this->prescription($admin, $medicine, 6);

        $dispensing = app(PharmacyDispensingService::class)->dispense($prescription, [[
            'prescription_item_id' => $prescription->items()->first()->id,
            'medicine_id' => $medicine->id,
            'quantity' => 6,
        ]], $location, $admin);

        $this->assertSame('completed', $dispensing->status->value);
        $this->assertSame('dispensed', $prescription->refresh()->status->value);
        $this->assertDatabaseHas('medicine_batches', ['batch_number' => 'DSP', 'available_quantity' => 14]);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'dispensing', 'quantity' => 6]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'medicine_dispensed']);
    }

    public function test_expiry_command_marks_expired_batches(): void
    {
        $admin = $this->bootstrappedFacility();
        [$medicine, $supplier, $location] = $this->catalog();
        $batch = $this->receiveBatch($admin, $medicine, $supplier, $location, 'OLD', today()->subDay()->toDateString(), 5);

        $this->artisan('pharmacy:refresh-expiry-statuses')->assertSuccessful();

        $this->assertSame('expired', $batch->refresh()->status->value);
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

        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, ServiceCategorySeeder::class, MedicineCategorySeeder::class, GenericMedicineSeeder::class, DosageFormSeeder::class, MedicineUnitSeeder::class, MedicineRouteSeeder::class, StockLocationSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    private function catalog(): array
    {
        $facility = currentFacility();
        $serviceCategory = ServiceCategory::query()->where('facility_id', $facility->id)->where('code', 'PHA')->firstOrFail();
        $service = Service::query()->create(['facility_id' => $facility->id, 'service_category_id' => $serviceCategory->id, 'name' => 'Test Medicine', 'code' => 'TMED', 'service_type' => ServiceType::Medicine, 'requires_payment' => true, 'is_active' => true]);
        ServicePrice::query()->create(['facility_id' => $facility->id, 'service_id' => $service->id, 'payer_type' => 'cash', 'amount' => 100, 'currency' => 'TZS', 'is_active' => true]);
        $unit = \App\Models\MedicineUnit::query()->where('facility_id', $facility->id)->firstOrFail();
        $medicine = Medicine::query()->create(['facility_id' => $facility->id, 'service_id' => $service->id, 'name' => 'Test Medicine', 'code' => 'TMED', 'purchase_unit_id' => $unit->id, 'dispensing_unit_id' => $unit->id, 'pack_size' => 1, 'purchase_to_dispensing_factor' => 1, 'reorder_level' => 5, 'default_dispensing_price' => 100, 'is_active' => true]);
        $supplier = Supplier::query()->create(['facility_id' => $facility->id, 'name' => 'Test Supplier', 'code' => 'SUP', 'phone_primary' => '0712000000', 'supplier_type' => 'pharmaceutical_wholesaler', 'is_active' => true]);
        $location = StockLocation::query()->where('facility_id', $facility->id)->where('is_receiving_location', true)->where('is_dispensing_location', true)->firstOrFail();

        return [$medicine, $supplier, $location];
    }

    private function receiveBatch(User $admin, Medicine $medicine, Supplier $supplier, StockLocation $location, string $batchNumber, string $expiry, int $quantity): MedicineBatch
    {
        app(StockReceivingService::class)->receive(['supplier_id' => $supplier->id, 'stock_location_id' => $location->id], [[
            'medicine_id' => $medicine->id,
            'batch_number' => $batchNumber,
            'expiry_date' => $expiry,
            'quantity_received' => $quantity,
            'unit_cost' => 10,
        ]], $admin);

        return MedicineBatch::query()->where('batch_number', $batchNumber)->firstOrFail();
    }

    private function prescription(User $admin, Medicine $medicine, int $quantity): Prescription
    {
        $facility = currentFacility();
        $department = Department::query()->where('facility_id', $facility->id)->firstOrFail();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'created_by' => $admin->id]);
        $visit = Visit::factory()->create(['facility_id' => $facility->id, 'patient_id' => $patient->id, 'visit_type' => 'new_patient', 'destination_department_id' => $department->id, 'current_department_id' => $department->id, 'created_by' => $admin->id]);
        $encounter = ClinicalEncounter::factory()->create(['facility_id' => $facility->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'department_id' => $department->id, 'provider_user_id' => $admin->id, 'created_by' => $admin->id]);
        $prescription = Prescription::query()->create(['facility_id' => $facility->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'clinical_encounter_id' => $encounter->id, 'prescribed_by' => $admin->id, 'prescription_number' => 'RX-TEST-'.fake()->unique()->numberBetween(1000, 9999), 'status' => 'prescribed', 'prescribed_at' => now(), 'created_by' => $admin->id]);
        PrescriptionItem::query()->create(['prescription_id' => $prescription->id, 'medicine_id' => $medicine->id, 'service_id' => $medicine->service_id, 'medication_name' => $medicine->name, 'dose' => '1 tab', 'frequency' => 'TDS', 'duration_value' => 2, 'duration_unit' => 'days', 'quantity' => $quantity, 'remaining_quantity' => $quantity, 'status' => 'prescribed', 'created_by' => $admin->id]);

        return $prescription->refresh();
    }
}
