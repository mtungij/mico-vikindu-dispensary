<?php

namespace Tests\Feature\Pharmacy;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Enums\ServiceType;
use App\Livewire\Pharmacy\Queue as PharmacyQueue;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineUnit;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\Permission;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Visit;
use App\Services\PharmacyBatchAllocationService;
use App\Services\PharmacyDispensingService;
use App\Services\PrescriptionService;
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
use Illuminate\Support\Facades\DB;
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
        $queue = $this->pharmacyQueue($prescription, $admin);
        $prescription->encounter->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $admin->id]);

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
        $this->assertSame('completed', $queue->refresh()->queue_status->value);
        $this->assertSame('completed', $prescription->visit->refresh()->visit_status->value);
    }

    public function test_partial_dispensing_keeps_pharmacy_queue_and_visit_active(): void
    {
        $admin = $this->bootstrappedFacility();
        [$medicine, $supplier, $location] = $this->catalog();
        $this->receiveBatch($admin, $medicine, $supplier, $location, 'PART', today()->addYear()->toDateString(), 20);
        $prescription = $this->prescription($admin, $medicine, 6);
        $queue = $this->pharmacyQueue($prescription, $admin);
        $prescription->encounter->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $admin->id]);

        app(PharmacyDispensingService::class)->dispense($prescription, [[
            'prescription_item_id' => $prescription->items()->first()->id,
            'medicine_id' => $medicine->id,
            'quantity' => 3,
        ]], $location, $admin);

        $this->assertSame('partially_dispensed', $prescription->refresh()->status->value);
        $this->assertSame('serving', $queue->refresh()->queue_status->value);
        $this->assertSame('awaiting_pharmacy', $prescription->visit->refresh()->visit_status->value);
        $this->assertSame(6.0, (float) $prescription->items()->firstOrFail()->invoiceItem->quantity);
    }

    public function test_dispensing_label_prints_actual_quantity_and_complete_medication_directions(): void
    {
        $admin = $this->bootstrappedFacility();
        [$medicine, $supplier, $location] = $this->catalog();
        $this->receiveBatch($admin, $medicine, $supplier, $location, 'LABEL-FULL', today()->addYear()->toDateString(), 20);
        $prescription = $this->prescription($admin, $medicine, 7);
        $prescription->patient->update(['first_name' => 'Emmanuel', 'middle_name' => null, 'last_name' => 'Michael', 'patient_number' => 'PAT-2026-000049', 'passport_photo_path' => null]);
        $prescription->items()->firstOrFail()->update([
            'dose' => '1 tablet',
            'frequency' => 'Once daily',
            'duration_value' => 7,
            'duration_unit' => 'days',
            'route' => 'Oral',
            'instructions' => 'Take after food',
        ]);
        $prescription->encounter->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $admin->id]);
        $dispensing = app(PharmacyDispensingService::class)->dispense($prescription, [[
            'prescription_item_id' => $prescription->items()->first()->id,
            'medicine_id' => $medicine->id,
            'quantity' => 7,
        ]], $location, $admin);

        $readOnlyFingerprint = json_encode([
            'stock_quantity' => (string) MedicineBatch::query()->sum('available_quantity'),
            'stock_movements' => StockMovement::query()->count(),
            'payments' => DB::table('payments')->count(),
            'invoice' => $prescription->visit->invoice->only(['subtotal', 'total_amount', 'paid_amount', 'balance_amount', 'updated_at']),
            'prescription_item' => $prescription->items()->sole()->only(['quantity', 'dispensed_quantity', 'remaining_quantity', 'updated_at']),
            'dispensing' => $dispensing->only(['status', 'payment_status', 'dispensed_at', 'updated_at']),
        ]);
        $response = $this->actingAs($admin)->get(route('pharmacy.dispensings.labels', $dispensing));

        $response->assertOk()
            ->assertSeeText('Dispensing Medicine Labels')
            ->assertSeeText('Management System')
            ->assertSeeText('Tafuta kwenye mfumo')
            ->assertSeeText('Patient Information')
            ->assertSeeText('Emmanuel Michael')
            ->assertSeeText('PAT-2026-000049')
            ->assertSee('data-testid="patient-initials"', false)
            ->assertSeeText('Dispensing Information')
            ->assertSeeText($location->name)
            ->assertSeeText('Medicine List')
            ->assertSeeText('Label Preview')
            ->assertSeeText('Print Medicine Label')
            ->assertSeeText('Print All Labels')
            ->assertSeeText('Qty: 7')
            ->assertSeeText('Take:')
            ->assertSeeText('1 tablet')
            ->assertSeeText('Frequency:')
            ->assertSeeText('Once daily')
            ->assertSeeText('Duration:')
            ->assertSeeText('7 days')
            ->assertSeeText('Route:')
            ->assertSeeText('Oral')
            ->assertSeeText('Instructions:')
            ->assertSeeText('Take after food');

        $item = $dispensing->items()->sole();
        $this->actingAs($admin)->get(route('pharmacy.dispensings.labels.item.print', [$dispensing, $item]))
            ->assertOk()
            ->assertSeeText('Print Medicine Label')
            ->assertSeeText('Quantity:')
            ->assertSeeText('7')
            ->assertSeeText('Emmanuel Michael')
            ->assertSee('page-break-inside: avoid', false)
            ->assertSee('.no-print { display: none !important; }', false)
            ->assertDontSeeText('Management System')
            ->assertDontSeeText('Patient Information');
        $this->assertSame(7.0, (float) $item->dispensed_quantity);
        $this->assertSame($readOnlyFingerprint, json_encode([
            'stock_quantity' => (string) MedicineBatch::query()->sum('available_quantity'),
            'stock_movements' => StockMovement::query()->count(),
            'payments' => DB::table('payments')->count(),
            'invoice' => $prescription->visit->invoice->fresh()->only(['subtotal', 'total_amount', 'paid_amount', 'balance_amount', 'updated_at']),
            'prescription_item' => $prescription->items()->sole()->fresh()->only(['quantity', 'dispensed_quantity', 'remaining_quantity', 'updated_at']),
            'dispensing' => $dispensing->fresh()->only(['status', 'payment_status', 'dispensed_at', 'updated_at']),
        ]));
    }

    public function test_partial_dispensing_labels_each_print_only_that_event_quantity_and_immutable_instructions(): void
    {
        $admin = $this->bootstrappedFacility();
        [$medicine, $supplier, $location] = $this->catalog();
        $this->receiveBatch($admin, $medicine, $supplier, $location, 'LABEL-PART', today()->addYear()->toDateString(), 20);
        $prescription = $this->prescription($admin, $medicine, 10);
        $item = $prescription->items()->firstOrFail();
        $item->update(['instructions' => 'Take after food']);
        $this->pharmacyQueue($prescription, $admin);
        $prescription->encounter->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $admin->id]);

        $first = app(PharmacyDispensingService::class)->dispense($prescription, [[
            'prescription_item_id' => $item->id,
            'medicine_id' => $medicine->id,
            'quantity' => 4,
        ]], $location, $admin);
        $item->refresh()->update(['instructions' => 'Changed after first dispensing']);
        $second = app(PharmacyDispensingService::class)->dispense($prescription->refresh(), [[
            'prescription_item_id' => $item->id,
            'medicine_id' => $medicine->id,
            'quantity' => 6,
        ]], $location, $admin);

        $this->actingAs($admin)->get(route('pharmacy.dispensings.labels.item.print', [$first, $first->items()->sole()]))
            ->assertOk()
            ->assertSeeText('Quantity:')
            ->assertSeeText('4')
            ->assertSeeText('Three times daily')
            ->assertDontSee('font-extrabold">10</dd>', false)
            ->assertSeeText('Instructions:')
            ->assertSeeText('Take after food')
            ->assertDontSeeText('Changed after first dispensing');
        $this->actingAs($admin)->get(route('pharmacy.dispensings.labels.item.print', [$second, $second->items()->sole()]))
            ->assertOk()
            ->assertSeeText('Quantity:')
            ->assertSeeText('6')
            ->assertDontSee('font-extrabold">10</dd>', false)
            ->assertSeeText('Instructions:')
            ->assertSeeText('Changed after first dispensing');
    }

    public function test_multiple_item_label_uses_each_actual_substituted_medicine_and_omits_empty_optional_fields(): void
    {
        $admin = $this->bootstrappedFacility();
        [$prescribedMedicine, $supplier, $location] = $this->catalog();
        $substitute = $this->additionalMedicine('Actual Substitute Medicine', 'SUB-A');
        $secondMedicine = $this->additionalMedicine('Second Dispensed Medicine', 'MED-B');
        $this->receiveBatch($admin, $substitute, $supplier, $location, 'LABEL-SUB', today()->addYear()->toDateString(), 10);
        $this->receiveBatch($admin, $secondMedicine, $supplier, $location, 'LABEL-MULTI', today()->addYear()->toDateString(), 10);
        $prescription = $this->prescription($admin, $prescribedMedicine, 2);
        $firstItem = $prescription->items()->firstOrFail();
        $secondItem = $this->addPrescriptionItem($prescription, $secondMedicine, 3, $admin);
        $prescription->encounter->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $admin->id]);

        $dispensing = app(PharmacyDispensingService::class)->dispense($prescription->refresh(), [
            ['prescription_item_id' => $firstItem->id, 'medicine_id' => $substitute->id, 'quantity' => 2, 'substitution_reason' => 'Equivalent supplied'],
            ['prescription_item_id' => $secondItem->id, 'medicine_id' => $secondMedicine->id, 'quantity' => 3],
        ], $location, $admin);

        $normal = $this->actingAs($admin)->get(route('pharmacy.dispensings.labels', $dispensing));
        $normal->assertOk()
            ->assertSeeText('Actual Substitute Medicine')
            ->assertSeeText('Second Dispensed Medicine')
            ->assertDontSeeText('Test Medicine')
            ->assertSeeText('Qty: 2')
            ->assertSeeText('Qty: 3')
            ->assertDontSeeText('Route:')
            ->assertDontSeeText('Instructions:');
        $this->assertSame(2, substr_count($normal->getContent(), 'Print Medicine Label'));

        $firstDispensingItem = $dispensing->items()->where('medicine_id', $substitute->id)->sole();
        $this->actingAs($admin)->get(route('pharmacy.dispensings.labels.item.print', [$dispensing, $firstDispensingItem]))
            ->assertOk()
            ->assertSeeText('Actual Substitute Medicine')
            ->assertDontSeeText('Second Dispensed Medicine');
        $all = $this->actingAs($admin)->get(route('pharmacy.dispensings.labels.print', $dispensing));
        $all->assertOk()
            ->assertSeeText('Print All Labels')
            ->assertSeeText('Actual Substitute Medicine')
            ->assertSeeText('Second Dispensed Medicine')
            ->assertDontSeeText('Management System');
        $this->assertSame(2, substr_count($all->getContent(), 'class="medicine-label'));
    }

    public function test_dispensing_labels_remain_hidden_across_facilities(): void
    {
        $admin = $this->bootstrappedFacility();
        [$medicine, $supplier, $location] = $this->catalog();
        $this->receiveBatch($admin, $medicine, $supplier, $location, 'LABEL-FAC', today()->addYear()->toDateString(), 5);
        $prescription = $this->prescription($admin, $medicine, 1);
        $prescription->encounter->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $admin->id]);
        $dispensing = app(PharmacyDispensingService::class)->dispense($prescription, [[
            'prescription_item_id' => $prescription->items()->first()->id,
            'medicine_id' => $medicine->id,
            'quantity' => 1,
        ]], $location, $admin);
        $otherFacility = Facility::factory()->create(['created_by' => $admin->id, 'updated_by' => $admin->id]);
        $dispensing->update(['facility_id' => $otherFacility->id]);

        $item = $dispensing->items()->sole();
        $this->actingAs($admin)->get(route('pharmacy.dispensings.labels', $dispensing))->assertNotFound();
        $this->actingAs($admin)->get(route('pharmacy.dispensings.labels.print', $dispensing))->assertNotFound();
        $this->actingAs($admin)->get(route('pharmacy.dispensings.labels.item.print', [$dispensing, $item]))->assertNotFound();
    }

    public function test_prescription_cancellation_cancels_pharmacy_queue_and_closes_visit(): void
    {
        $admin = $this->bootstrappedFacility();
        [$medicine] = $this->catalog();
        $prescription = $this->prescription($admin, $medicine, 6);
        $queue = $this->pharmacyQueue($prescription, $admin);
        $prescription->encounter->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $admin->id]);

        app(PrescriptionService::class)->cancelPrescription($prescription, 'Patient declined medicine', $admin);

        $this->assertSame('cancelled', $prescription->refresh()->status->value);
        $this->assertSame('cancelled', $queue->refresh()->queue_status->value);
        $this->assertSame('completed', $prescription->visit->refresh()->visit_status->value);
    }

    public function test_dispensing_reversal_restores_stock_and_reopens_prescription_and_pharmacy_workflow(): void
    {
        $admin = $this->bootstrappedFacility();
        [$medicine, $supplier, $location] = $this->catalog();
        $this->receiveBatch($admin, $medicine, $supplier, $location, 'REV', today()->addYear()->toDateString(), 20);
        $prescription = $this->prescription($admin, $medicine, 6);
        $this->pharmacyQueue($prescription, $admin);
        $prescription->encounter->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $admin->id]);
        $dispensing = app(PharmacyDispensingService::class)->dispense($prescription, [[
            'prescription_item_id' => $prescription->items()->first()->id,
            'medicine_id' => $medicine->id,
            'quantity' => 6,
        ]], $location, $admin);

        app(PharmacyDispensingService::class)->reverseDispensing($dispensing, $admin, 'Dispensed in error');

        $item = $prescription->items()->firstOrFail()->refresh();
        $this->assertSame('reversed', $dispensing->refresh()->status->value);
        $this->assertSame('prescribed', $prescription->refresh()->status->value);
        $this->assertSame(0.0, (float) $item->dispensed_quantity);
        $this->assertSame(6.0, (float) $item->remaining_quantity);
        $this->assertDatabaseHas('medicine_batches', ['batch_number' => 'REV', 'available_quantity' => 20]);
        $this->assertSame(1, PatientQueue::query()->where('visit_id', $prescription->visit_id)->whereHas('department', fn ($query) => $query->where('code', 'PHA'))->whereIn('queue_status', ['waiting', 'called', 'serving'])->count());
        $this->assertSame('awaiting_pharmacy', $prescription->visit->refresh()->visit_status->value);
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
        $unit = MedicineUnit::query()->where('facility_id', $facility->id)->firstOrFail();
        $medicine = Medicine::query()->create(['facility_id' => $facility->id, 'service_id' => $service->id, 'name' => 'Test Medicine', 'code' => 'TMED', 'purchase_unit_id' => $unit->id, 'dispensing_unit_id' => $unit->id, 'pack_size' => 1, 'purchase_to_dispensing_factor' => 1, 'reorder_level' => 5, 'default_dispensing_price' => 100, 'is_active' => true]);
        $supplier = Supplier::query()->create(['facility_id' => $facility->id, 'name' => 'Test Supplier', 'code' => 'SUP', 'phone_primary' => '0712000000', 'supplier_type' => 'pharmaceutical_wholesaler', 'is_active' => true]);
        $location = StockLocation::query()->where('facility_id', $facility->id)->where('is_receiving_location', true)->where('is_dispensing_location', true)->firstOrFail();

        return [$medicine, $supplier, $location];
    }

    private function additionalMedicine(string $name, string $code): Medicine
    {
        $facility = currentFacility();
        $category = ServiceCategory::query()->where('facility_id', $facility->id)->where('code', 'PHA')->firstOrFail();
        $service = Service::query()->create(['facility_id' => $facility->id, 'service_category_id' => $category->id, 'name' => $name, 'code' => $code, 'service_type' => ServiceType::Medicine, 'requires_payment' => true, 'is_active' => true]);
        ServicePrice::query()->create(['facility_id' => $facility->id, 'service_id' => $service->id, 'payer_type' => 'cash', 'amount' => 100, 'currency' => 'TZS', 'is_active' => true]);
        $unit = MedicineUnit::query()->where('facility_id', $facility->id)->firstOrFail();

        return Medicine::query()->create(['facility_id' => $facility->id, 'service_id' => $service->id, 'name' => $name, 'code' => $code, 'purchase_unit_id' => $unit->id, 'dispensing_unit_id' => $unit->id, 'pack_size' => 1, 'purchase_to_dispensing_factor' => 1, 'reorder_level' => 0, 'is_active' => true]);
    }

    private function addPrescriptionItem(Prescription $prescription, Medicine $medicine, int $quantity, User $admin): PrescriptionItem
    {
        $item = PrescriptionItem::query()->create(['prescription_id' => $prescription->id, 'medicine_id' => $medicine->id, 'service_id' => $medicine->service_id, 'medication_name' => $medicine->name, 'dose' => '1 tablet', 'frequency' => 'Once daily', 'duration_value' => 3, 'duration_unit' => 'days', 'quantity' => $quantity, 'remaining_quantity' => $quantity, 'status' => 'prescribed', 'created_by' => $admin->id]);
        $invoiceItem = $prescription->visit->invoice->items()->create(['facility_id' => $prescription->facility_id, 'patient_id' => $prescription->patient_id, 'visit_id' => $prescription->visit_id, 'service_id' => $medicine->service_id, 'item_type' => 'medicine', 'reference_type' => PrescriptionItem::class, 'reference_id' => $item->id, 'description' => $medicine->name, 'description_snapshot' => $medicine->name, 'quantity' => $quantity, 'unit_price' => 100, 'gross_amount' => $quantity * 100, 'total_amount' => $quantity * 100, 'payer_amount' => 0, 'patient_amount' => 0, 'insurance_amount' => $quantity * 100, 'paid_amount' => 0, 'net_amount' => $quantity * 100, 'status' => 'covered', 'created_by' => $admin->id]);
        $item->update(['invoice_item_id' => $invoiceItem->id, 'unit_price_snapshot' => 100, 'patient_amount' => 0, 'insurance_amount' => $quantity * 100, 'payer_amount' => $quantity * 100]);

        return $item->refresh();
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
        $item = PrescriptionItem::query()->create(['prescription_id' => $prescription->id, 'medicine_id' => $medicine->id, 'service_id' => $medicine->service_id, 'medication_name' => $medicine->name, 'dose' => '1 tab', 'frequency' => 'TDS', 'duration_value' => 2, 'duration_unit' => 'days', 'quantity' => $quantity, 'remaining_quantity' => $quantity, 'status' => 'prescribed', 'created_by' => $admin->id]);
        $invoice = Invoice::query()->create(['facility_id' => $facility->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'invoice_number' => 'INV-RX-'.fake()->unique()->numberBetween(1000, 9999), 'payer_type' => 'cash', 'invoice_status' => 'paid', 'status' => 'paid', 'payment_status' => 'paid', 'issued_at' => now(), 'created_by' => $admin->id]);
        $invoiceItem = $invoice->items()->create(['facility_id' => $facility->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'service_id' => $medicine->service_id, 'item_type' => 'medicine', 'reference_type' => PrescriptionItem::class, 'reference_id' => $item->id, 'description' => $medicine->name, 'description_snapshot' => $medicine->name, 'quantity' => $quantity, 'unit_price' => 100, 'gross_amount' => $quantity * 100, 'total_amount' => $quantity * 100, 'payer_amount' => 0, 'patient_amount' => 0, 'insurance_amount' => $quantity * 100, 'paid_amount' => 0, 'net_amount' => $quantity * 100, 'status' => 'covered', 'created_by' => $admin->id]);
        $item->update(['invoice_item_id' => $invoiceItem->id, 'unit_price_snapshot' => 100, 'patient_amount' => 0, 'insurance_amount' => $quantity * 100, 'payer_amount' => $quantity * 100]);

        return $prescription->refresh();
    }

    private function pharmacyQueue(Prescription $prescription, User $admin): PatientQueue
    {
        $department = Department::query()->where('facility_id', currentFacility()->id)->where('code', 'PHA')->firstOrFail();
        $queue = PatientQueue::query()->create([
            'facility_id' => currentFacility()->id,
            'visit_id' => $prescription->visit_id,
            'patient_id' => $prescription->patient_id,
            'department_id' => $department->id,
            'queue_number' => 'PHA-TEST-'.fake()->unique()->numerify('###'),
            'queue_date' => today(),
            'queue_status' => 'waiting',
            'priority' => 'normal',
            'position' => 1,
            'checked_in_at' => now(),
            'created_by' => $admin->id,
        ]);
        $prescription->visit->update([
            'visit_status' => 'awaiting_pharmacy',
            'current_department_id' => $department->id,
            'current_queue_id' => $queue->id,
        ]);

        return $queue;
    }
}
