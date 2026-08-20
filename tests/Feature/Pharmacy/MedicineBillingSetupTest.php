<?php

namespace Tests\Feature\Pharmacy;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Enums\PayerType;
use App\Livewire\Pharmacy\Medicines\Index as MedicinesIndex;
use App\Models\Facility;
use App\Models\Medicine;
use App\Models\MedicineUnit;
use App\Models\Permission;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\MedicineBillingReadinessService;
use App\Services\MedicineBillingSetupService;
use App\Services\MedicineCatalogService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\MedicineUnitSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MedicineBillingSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_medicine_cash_price_creates_idempotent_service_and_versioned_price(): void
    {
        $admin = $this->bootstrappedFacility();
        $unit = MedicineUnit::query()->forCurrentFacility()->firstOrFail();
        $data = $this->medicineData($unit, 'Amoxicillin 500mg', 'AMOX500', '500');

        Livewire::actingAs($admin)->test(MedicinesIndex::class)
            ->call('create')
            ->set('form.name', $data['name'])
            ->set('form.code', $data['code'])
            ->set('form.purchase_unit_id', $unit->id)
            ->set('form.dispensing_unit_id', $unit->id)
            ->set('form.cash_price', '500')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);
        $medicine = Medicine::query()->where('code', 'AMOX500')->sole();
        $service = $medicine->service;

        $this->assertNotNull($service);
        $this->assertSame('MED-AMOX500', $service->code);
        $this->assertSame('medicine', $service->service_type->value);
        $this->assertStringContainsString("[SYSTEM_MEDICINE:{$medicine->id}]", $service->description);
        $this->assertSame($service->id, $medicine->service_id);
        $this->assertDatabaseHas('service_prices', ['service_id' => $service->id, 'payer_type' => 'cash', 'amount' => 500, 'is_active' => true]);
        $this->assertTrue(app(MedicineBillingReadinessService::class)->inspectForPayer($medicine->refresh(), $medicine->facility_id, PayerType::Cash)['ready']);
        $this->assertNull($medicine->default_dispensing_price);

        app(MedicineCatalogService::class)->updateMedicine($medicine, $data, $admin);
        $this->assertSame(1, Service::query()->where('facility_id', $medicine->facility_id)->where('code', 'MED-AMOX500')->count());
        $this->assertSame(1, ServicePrice::query()->where('service_id', $service->id)->where('payer_type', 'cash')->where('is_active', true)->count());
        $this->assertSame(1, ServicePrice::query()->where('service_id', $service->id)->where('payer_type', 'cash')->count());

        app(MedicineCatalogService::class)->updateMedicine($medicine->refresh(), [...$data, 'cash_price' => '600'], $admin);
        $this->assertDatabaseHas('service_prices', ['service_id' => $service->id, 'payer_type' => 'cash', 'amount' => 500, 'is_active' => false]);
        $this->assertDatabaseHas('service_prices', ['service_id' => $service->id, 'payer_type' => 'cash', 'amount' => 600, 'is_active' => true]);
        $this->assertSame(2, ServicePrice::query()->where('service_id', $service->id)->where('payer_type', 'cash')->count());
        $this->assertDatabaseHas('activity_logs', ['event' => 'medicine_billing_service_created', 'subject_id' => $medicine->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'medicine_cash_price_changed', 'subject_id' => $medicine->id]);
    }

    public function test_custom_service_and_insurance_price_are_preserved_and_inactive_mapping_is_not_replaced(): void
    {
        $admin = $this->bootstrappedFacility();
        $unit = MedicineUnit::query()->forCurrentFacility()->firstOrFail();
        $category = ServiceCategory::query()->where('code', 'PHA')->firstOrFail();
        $custom = Service::query()->create(['facility_id' => currentFacility()->id, 'service_category_id' => $category->id, 'name' => 'Custom Antibiotic Billing', 'code' => 'CUSTOM-AMOX', 'service_type' => 'medicine', 'requires_payment' => true, 'is_active' => true]);
        ServicePrice::query()->create(['facility_id' => currentFacility()->id, 'service_id' => $custom->id, 'payer_type' => 'insurance', 'insurance_provider_id' => null, 'amount' => 900, 'currency' => 'TZS', 'is_active' => true]);

        $medicine = app(MedicineCatalogService::class)->createMedicine([...$this->medicineData($unit, 'Custom Amoxicillin', 'CAMOX', '450'), 'service_id' => $custom->id], $admin);
        $this->assertSame($custom->id, $medicine->service_id);
        $this->assertDatabaseHas('service_prices', ['service_id' => $custom->id, 'payer_type' => 'insurance', 'amount' => 900, 'is_active' => true]);
        $this->assertDatabaseHas('service_prices', ['service_id' => $custom->id, 'payer_type' => 'cash', 'amount' => 450, 'is_active' => true]);

        $replacement = Service::query()->create(['facility_id' => currentFacility()->id, 'service_category_id' => $category->id, 'name' => 'Replacement Antibiotic Billing', 'code' => 'CUSTOM-AMOX-2', 'service_type' => 'medicine', 'requires_payment' => true, 'is_active' => true]);
        $medicine = app(MedicineCatalogService::class)->updateMedicine($medicine, [...$this->medicineData($unit, 'Custom Amoxicillin', 'CAMOX', '450'), 'service_id' => $replacement->id], $admin);
        $this->assertDatabaseHas('activity_logs', ['event' => 'medicine_billing_service_manual_correction', 'subject_id' => $medicine->id]);
        $this->assertSame($replacement->id, $medicine->service_id);

        $replacement->update(['is_active' => false]);
        try {
            app(MedicineCatalogService::class)->updateMedicine($medicine->refresh(), [...$this->medicineData($unit, 'Custom Amoxicillin', 'CAMOX', '500'), 'service_id' => $replacement->id], $admin);
            $this->fail('Inactive custom service was silently replaced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('service_id', $exception->errors());
        }
        $this->assertSame($replacement->id, $medicine->refresh()->service_id);
        $this->assertSame(0, Service::query()->where('code', 'MED-CAMOX')->count());
    }

    public function test_bulk_command_is_dry_run_by_default_and_requires_explicit_reference_price_approval(): void
    {
        $admin = $this->bootstrappedFacility();
        $unit = MedicineUnit::query()->forCurrentFacility()->firstOrFail();
        $safe = $this->legacyMedicine($unit, 'Legacy Safe Medicine', 'LEGSAFE', 125);
        $manual = $this->legacyMedicine($unit, 'Legacy Manual Medicine', 'LEGMAN', 200);
        $invalid = $this->legacyMedicine($unit, 'Legacy Invalid Medicine', 'LEGINV', 0);
        $ambiguous = $this->legacyMedicine($unit, 'Legacy Ambiguous Medicine', 'LEGAMB', 300);
        $category = ServiceCategory::query()->where('code', 'PHA')->firstOrFail();
        Service::query()->create(['facility_id' => currentFacility()->id, 'service_category_id' => $category->id, 'name' => $ambiguous->name, 'code' => app(MedicineBillingSetupService::class)->managedCode($ambiguous), 'service_type' => 'consultation', 'requires_payment' => true, 'is_active' => true]);
        $customService = Service::query()->create(['facility_id' => currentFacility()->id, 'service_category_id' => $category->id, 'name' => 'Historical Custom Mapping', 'code' => 'HIST-CUSTOM', 'service_type' => 'medicine', 'requires_payment' => true, 'is_active' => true]);
        $customMapped = $this->legacyMedicine($unit, 'Legacy Custom Medicine', 'LEGCUSTOM', 350);
        $customMapped->update(['service_id' => $customService->id]);

        $this->artisan('pharmacy:setup-medicine-billing', ['--ids' => [$safe->id, $manual->id, $invalid->id, $ambiguous->id]])
            ->expectsOutputToContain('Dry run complete')
            ->expectsOutputToContain('safe_missing_service')
            ->expectsOutputToContain('invalid_reference_price')
            ->expectsOutputToContain('ambiguous_configuration')
            ->assertSuccessful();
        $this->assertNull($safe->refresh()->service_id);
        $this->assertDatabaseCount('service_prices', 0);

        $this->artisan('pharmacy:setup-medicine-billing', ['--apply' => true, '--actor' => $admin->id, '--ids' => [$manual->id]])
            ->expectsOutputToContain('changed: 0')
            ->assertSuccessful();
        $this->assertNull($manual->refresh()->service_id);

        $this->artisan('pharmacy:setup-medicine-billing', ['--apply' => true, '--approve-reference-price' => true, '--actor' => $admin->id, '--ids' => [$safe->id]])
            ->expectsOutputToContain('changed: 1')
            ->assertSuccessful();
        $this->assertNotNull($safe->refresh()->service_id);
        $this->assertDatabaseHas('service_prices', ['service_id' => $safe->service_id, 'payer_type' => 'cash', 'amount' => 125, 'is_active' => true]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'medicine_billing_bulk_setup', 'subject_id' => $safe->id]);
        $this->assertNull($invalid->refresh()->service_id);
        $this->assertNull($ambiguous->refresh()->service_id);

        $this->artisan('pharmacy:setup-medicine-billing', ['--apply' => true, '--approve-reference-price' => true, '--actor' => $admin->id, '--ids' => [$customMapped->id]])
            ->expectsOutputToContain('manual_review')
            ->expectsOutputToContain('changed: 0')
            ->assertSuccessful();
        $this->assertSame($customService->id, $customMapped->refresh()->service_id);
        $this->assertDatabaseMissing('service_prices', ['service_id' => $customService->id]);
    }

    public function test_setup_rejects_unauthorized_actor_and_preserves_cross_facility_isolation(): void
    {
        $admin = $this->bootstrappedFacility();
        $unit = MedicineUnit::query()->forCurrentFacility()->firstOrFail();
        $medicine = $this->legacyMedicine($unit, 'Protected Medicine', 'PROTECTED', 100);
        $doctor = User::factory()->create();

        try {
            app(MedicineBillingSetupService::class)->setup($medicine, 100, $doctor);
            $this->fail('Unauthorized actor configured medicine billing.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertNull($medicine->refresh()->service_id);

        $otherFacility = Facility::factory()->create(['created_by' => $admin->id, 'updated_by' => $admin->id]);
        $otherUnit = MedicineUnit::query()->create(['facility_id' => $otherFacility->id, 'name' => 'Other Tablet', 'symbol' => 'otab', 'is_active' => true]);
        $foreign = Medicine::query()->create(['facility_id' => $otherFacility->id, 'purchase_unit_id' => $otherUnit->id, 'dispensing_unit_id' => $otherUnit->id, 'name' => 'Foreign Medicine', 'code' => 'FOREIGN', 'pack_size' => 1, 'purchase_to_dispensing_factor' => 1, 'default_dispensing_price' => 100, 'is_active' => true]);

        $this->artisan('pharmacy:setup-medicine-billing', ['--apply' => true, '--approve-reference-price' => true, '--actor' => $admin->id, '--facility' => currentFacility()->id, '--ids' => [$foreign->id]])->assertSuccessful();
        $this->assertNull($foreign->refresh()->service_id);
    }

    public function test_bulk_classifier_blocks_medicine_and_service_collisions(): void
    {
        $this->bootstrappedFacility();
        $unit = MedicineUnit::query()->forCurrentFacility()->firstOrFail();
        $category = ServiceCategory::query()->where('code', 'PHA')->firstOrFail();
        $setup = app(MedicineBillingSetupService::class);

        $duplicateA = $this->legacyMedicine($unit, 'Duplicate Medicine', 'DUP-A', 100);
        $duplicateB = $this->legacyMedicine($unit, 'duplicate medicine', 'DUP-B', 100);
        $codeCollisionA = $this->legacyMedicine($unit, 'Code Collision A', 'SAME CODE', 100);
        $codeCollisionB = $this->legacyMedicine($unit, 'Code Collision B', 'SAME-CODE', 100);
        $activeServiceCollision = $this->legacyMedicine($unit, 'Active Service Collision', 'ACTIVE-SVC', 100);
        $deletedServiceCollision = $this->legacyMedicine($unit, 'Deleted Service Collision', 'DELETED-SVC', 100);

        Service::query()->create(['facility_id' => currentFacility()->id, 'service_category_id' => $category->id, 'name' => $activeServiceCollision->name, 'code' => 'UNRELATED-ACTIVE', 'service_type' => 'medicine', 'requires_payment' => true, 'is_active' => true]);
        $deletedService = Service::query()->create(['facility_id' => currentFacility()->id, 'service_category_id' => $category->id, 'name' => 'Unrelated historical name', 'code' => $setup->managedCode($deletedServiceCollision), 'service_type' => 'medicine', 'requires_payment' => true, 'is_active' => false]);
        $deletedService->delete();

        foreach ([$duplicateA, $duplicateB, $codeCollisionA, $codeCollisionB, $activeServiceCollision, $deletedServiceCollision] as $medicine) {
            $classification = $setup->classifyForBulk($medicine, true);

            $this->assertSame('ambiguous_configuration', $classification['classification']);
            $this->assertSame('manual_review', $classification['risk']);
            $this->assertNull($classification['proposed_cash_price']);
        }
    }

    public function test_bulk_classifier_preserves_soft_deleted_historical_custom_mapping(): void
    {
        $this->bootstrappedFacility();
        $unit = MedicineUnit::query()->forCurrentFacility()->firstOrFail();
        $category = ServiceCategory::query()->where('code', 'PHA')->firstOrFail();
        $medicine = $this->legacyMedicine($unit, 'Paracetamol', 'PARA', 100);
        $service = Service::query()->create(['facility_id' => currentFacility()->id, 'service_category_id' => $category->id, 'name' => 'Historical Paracetamol', 'code' => 'HIST-PARA', 'service_type' => 'medicine', 'requires_payment' => true, 'is_active' => true]);
        $medicine->update(['service_id' => $service->id]);
        $service->delete();

        $classification = app(MedicineBillingSetupService::class)->classifyForBulk($medicine->refresh(), true);

        $this->assertSame('historical_custom_mapping', $classification['classification']);
        $this->assertSame('manual_review', $classification['risk']);
        $this->assertNull($classification['proposed_cash_price']);
        $this->assertSame($service->id, $medicine->refresh()->service_id);
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        Facility::query()->create(['name' => 'Billing Setup Dispensary', 'code' => 'BSD', 'facility_type' => FacilityType::Dispensary, 'ownership_type' => OwnershipType::Private, 'phone_primary' => '+255700000000', 'region' => 'Dar es Salaam', 'district' => 'Temeke', 'ward' => 'Vikindu', 'physical_address' => 'Vikindu', 'setup_completed_at' => now(), 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, ServiceCategorySeeder::class, MedicineUnitSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    private function medicineData(MedicineUnit $unit, string $name, string $code, string $cashPrice): array
    {
        return ['name' => $name, 'code' => $code, 'purchase_unit_id' => $unit->id, 'dispensing_unit_id' => $unit->id, 'pack_size' => 1, 'purchase_to_dispensing_factor' => 1, 'reorder_level' => 0, 'is_active' => true, 'cash_price' => $cashPrice];
    }

    private function legacyMedicine(MedicineUnit $unit, string $name, string $code, float $referencePrice): Medicine
    {
        return Medicine::query()->create(['facility_id' => currentFacility()->id, 'purchase_unit_id' => $unit->id, 'dispensing_unit_id' => $unit->id, 'name' => $name, 'code' => $code, 'pack_size' => 1, 'purchase_to_dispensing_factor' => 1, 'default_dispensing_price' => $referencePrice, 'is_active' => true]);
    }
}
